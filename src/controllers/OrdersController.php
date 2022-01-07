<?php
namespace fostercommerce\shipstationconnect\controllers;

use Craft;
use craft\web\Controller;
use craft\elements\MatrixBlock;
use craft\helpers\ElementHelper;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\db\Query;
use craft\db\Table;
use craft\models\MatrixBlockType;
use yii\web\HttpException;
use yii\base\ErrorException;
use yii\base\Event;
use fostercommerce\shipstationconnect\Plugin;
use fostercommerce\shipstationconnect\events\FindOrderEvent;
use fostercommerce\shipstationconnect\records\Shipment as ShipmentRecord;
use SimpleXMLElement;

class OrdersController extends Controller
{
    const FIND_ORDER_EVENT = 'findOrderEvent';

    // Disable CSRF validation for the entire controller
    public $enableCsrfValidation = false;

    protected $allowAnonymous = true;

    /**
     * @inheritDoc
     */
    public function init()
    {
        // Allow anonymous access only when this plugin is handling basic
        // authentication, otherwise require auth so that Craft doesn't let
        // unauthenticated requests go through.
        $isUsingCraftAuth = Plugin::getInstance()->isAuthHandledByCraft();
        $this->allowAnonymous = !$isUsingCraftAuth;
        if ($isUsingCraftAuth) {
            $this->requirePermission('shipstationconnect-processOrders');
        }

        return parent::init();
    }

    /**
     * ShipStation will hit this action for processing orders, both POSTing and GETting.
     *   ShipStation will send a GET param 'action' of either shipnotify or export.
     *   If this is not found or is any other string, this will throw a 400 exception.
     *
     * @param array $variables, containing key 'fulfillmentService'
     * @throws HttpException for malformed requests
     */
    public function actionProcess($store = null, $action = null)
    {
        $request = Craft::$app->request;
        try {
            if (!$this->authenticate()) {
                throw new HttpException(401, 'Invalid ShipStation username or password.');
            }

            switch ($action) {
                case 'export':
                    return $this->getOrders($store);
                case 'shipnotify':
                    return $this->postShipment();
                default:
                    throw new HttpException(400, 'No action set. Set the ?action= parameter as `export` or `shipnotify`.');
            }
        } catch (ErrorException $e) {
            $this->logException('Error processing action {action}', ['action' => $action], $e);
            return $this->asErrorJson($e->getMessage())->setStatusCode(500);
        } catch (HttpException $e) {
            $action = $action;
            if ($action) {
                $this->logException('Error processing action {action}', ['action' => $action], $e);
            } else {
                $this->logException('An action is required. Supported actions: export, shipnotify.');
            }

            return $this->asErrorJson($e->getMessage())->setStatusCode($e->statusCode);
        } catch (\Exception $e) {
            $this->logException('Error processing action {action}', ['action' => $action], $e);
            return $this->asErrorJson($e->getMessage())->setStatusCode(500);
        }
    }

    private function logException($msg, $params = [], $e = null)
    {
        Craft::error(
            Craft::t('shipstationconnect', $msg, $params),
            __METHOD__
        );

        if ($e) {
            Craft::$app->getErrorHandler()->logException($e);
        }
    }

    /**
     * Authenticate the user using HTTP Basic auth. This is *not* using Craft's sessions/authentication.
     *
     * @return bool, true if successfully authenticated or false otherwise
     */
    protected function authenticate()
    {
        $plugin = Plugin::getInstance();
        if ($plugin->isAuthHandledByCraft()) {
            return true;
        }

        $settings = $plugin->settings;
        $expectedUsername = Craft::parseEnv($settings->shipstationUsername);
        $expectedPassword = Craft::parseEnv($settings->shipstationPassword);

        list($username, $password) = Craft::$app->getRequest()->getAuthCredentials();

        return $expectedUsername === $username && $expectedPassword === $password;
    }

    /**
     * Returns a big XML object of all orders in a format described by ShipStation
     *
     * @return SimpleXMLElement Orders XML
     */
    protected function getOrders($store = null)
    {
        $query = Order::find();

        $start_date = $this->parseDate('start_date');
        $end_date = $this->parseDate('end_date');

        if ($start_date && $end_date) {
            $query->dateUpdated(array('and', '> '.$start_date, '< '.$end_date));
        }

        $query->isCompleted(true);

        $storeFieldHandle = Plugin::getInstance()->settings->storesFieldHandle;
        if ($store !== null || $storeFieldHandle !== '') {
            $field = Craft::$app->getFields()->getFieldByHandle($storeFieldHandle);
            $fieldColumnName = ElementHelper::fieldColumnFromField($field);
            $query->andWhere([$fieldColumnName => $store]);
        }

        $query->orderBy('dateUpdated asc');

        $num_pages = $this->paginateOrders($query);

        $parent_xml = new SimpleXMLElement('<Orders />');
        $parent_xml->addAttribute('pages', $num_pages);

        Plugin::getInstance()->xml->orders($parent_xml, $query->all());

        $this->returnXML($parent_xml);
    }

    /**
     * For a Criteria instance of Orders, return the number of total pages and apply a corresponding offset and limit
     *
     * @param ElementCriteriaModel $query, a REFERENCE to the criteria instance
     * @return int total number of pages
     */
    protected function paginateOrders(&$query)
    {
        $pageSize = Plugin::getInstance()->settings->ordersPageSize;
        if (!is_numeric($pageSize) || $pageSize < 1) {
            $pageSize = 25;
        }

        $numPages = ceil($query->count() / $pageSize);
        $pageNum = Craft::$app->getRequest()->getParam('page');
        if (!is_numeric($pageNum) || $pageNum < 1) {
            $pageNum = 1;
        }

        $query->limit($pageSize);
        $query->offset(($pageNum - 1) * $pageSize);

        return $numPages;
    }

    /**
     * For a given date field, parse and return its date as a string
     *
     * @param String $field_name, the name of the field in GET params
     * @return String|null the formatted date string
     */
    protected function parseDate($field_name)
    {
        $request = Craft::$app->getRequest();
        if ($date_raw = $request->getParam($field_name)) {
            $date = strtotime($date_raw);
            if ($date !== false) {
                if ($field_name === 'start_date') {
                    return date('Y-m-d H:i:s', $date);
                } else {
                    return date('Y-m-d H:i:59', $date);
                }
            }
        }
        return null;
    }

    private function getBlockTypeByHandle($fieldId, $handle)
    {
        $result = (new Query())
            ->select([
                'id',
                'fieldId',
                'fieldLayoutId',
                'name',
                'handle',
                'sortOrder',
                'uid'
            ])
            ->from([Table::MATRIXBLOCKTYPES])
            ->where(['fieldId' => $fieldId])
            ->andWhere(['handle' => $handle])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->one();

        if ($result) {
            return new MatrixBlockType($result);
        }

        return null;
    }

    /**
     * Updates order status for a given order. This is called by ShipStation.
     * The order is found using the XML node OrderNumber.
     *
     * See craft/plugins/commerce/controllers/Commerce_OrdersController.php#actionUpdateStatus() for details
     *
     * @throws ErrorException if the order fails to save
     */
    protected function postShipment()
    {
        $request = Craft::$app->getRequest();

        $shipNotice = json_decode(json_encode(new SimpleXMLElement($request->getRawBody())));

        $order = $this->orderFromBody($shipNotice);

        $settings = Plugin::getInstance()->settings;

        // Set default order status then change accordingly if saveShipmentItems is set
        $orderStatusHandle = $settings->shippedStatusHandle;
        if ($settings->saveShipmentItems) {
            $lineItemStatuses = CommercePlugin::getInstance()->getLineItemStatuses();
            $partiallyShipped = $lineItemStatuses
                ->getLineItemStatusByHandle($settings->partiallyShippedLineItemStatusHandle);
            $shipped = $lineItemStatuses
                ->getLineItemStatusByHandle($settings->shippedLineItemStatusHandle);

            $newQtys = [];
            if (is_array($shipNotice->Items->Item)) {
                foreach ($shipNotice->Items->Item as $item) {
                    $newQtys[$item->SKU] = $item->Quantity;
                }
            } else {
                $newQtys[$shipNotice->Items->Item->SKU] = $shipNotice->Items->Item->Quantity;
            }

            $shippedQtys = $this->getShippedQtys($order->id);

            $shippedLineItems = 0;
            $rows = [
                'save' => [],
                'update' => [],
                'delete' => []
            ];
            foreach ($order->lineItems as $lineItem) {
                if (isset($newQtys[$lineItem->sku])) {
                    $status = $this->getNewLineItemStatus(
                        $lineItem,
                        $newQtys[$lineItem->sku],
                        $shippedQtys[$lineItem->sku] ?? null
                    );

                    $lineItem->lineItemStatusId = $$status->id;
                }

                if ($lineItem->lineItemStatusId == $shipped->id) {
                    $shippedLineItems++;
                }
            }

            if ($shippedLineItems !== count($order->lineItems)) {
                $orderStatusHandle = $settings->partiallyShippedStatusHandle;
            }
        }

        $orderStatus = CommercePlugin::getInstance()
            ->orderStatuses
            ->getOrderStatusByHandle($orderStatusHandle);
        if (!$orderStatus) {
            throw new ErrorException("Failed to find shipped order status");
        }

        $order->orderStatusId = $orderStatus->id;
        $order->message = 'Marking order as shipped. Adding shipping information.';
        $shippingInformation = $this->getShippingInformationFromBody($shipNotice);

        $matrix = Craft::$app->fields->getFieldByHandle($settings->matrixFieldHandle);

        // If the field exists
        if ($matrix) {
            $blockType = $this->getBlockTypeByHandle($matrix->id, $settings->blockTypeHandle);

            if ($blockType && $order && $this->validateShippingInformation($shippingInformation)) {
                $block = new MatrixBlock([
                    'ownerId' => $order->id,
                    'fieldId' => $matrix->id,
                    'typeId' => $blockType->id,
                ]);
                $block->setFieldValues([
                    $settings->carrierFieldHandle => $shippingInformation['carrier'],
                    $settings->serviceFieldHandle => $shippingInformation['service'],
                    $settings->trackingNumberFieldHandle => $shippingInformation['trackingNumber']
                ]);

                if (Craft::$app->elements->saveElement($block)) {
                    if ($settings->saveShipmentItems) {
                        $shipment = new ShipmentRecord();
                        $shipment->orderId = $order->id;
                        $shipment->shipmentId = $block->getId();
                        $shipment->shippedQtys = json_encode($newQtys);
                        $shipment->save();
                    }
                } else {
                    Craft::warning(
                        Craft::t(
                            'shipstationconnect',
                            'Unable to save shipping information.'
                        ),
                        __METHOD__
                    );
                }
            }
        } else {
            Craft::warning(
                Craft::t(
                    'shipstationconnect',
                    'Missing shippingInfo Matrix field. Ignoring.'
                ),
                __METHOD__
            );
        }

        if (Craft::$app->elements->saveElement($order, false)) {
            return $this->asJson(['success' => true]);
        } else {
            throw new ErrorException('Failed to save order with id ' . $order->id);
        }
    }

    private function validateShippingInformation($info)
    {
        // Requires at least one value
        foreach ($info as $key => $value) {
            if ($value && !empty(trim($value))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse XML nodes POSTed from ShipStation for fields available to us on the Order's shippingInfo matrix field
     *
     * Note: only fields that exist in the matrix block will be set.
     *       ShipStation posts, in XML, many more fields than these, but for now we disregard.
     *       https://help.shipstation.com/hc/en-us/articles/205928478-ShipStation-Custom-Store-Development-Guide#2ai
     *
     * @param object $body
     * @return array
     */
    protected function getShippingInformationFromBody(object $body)
    {
        // Test is_scalar to weed out empty properties converted to StdClass objects by json_encode
        return [
            'carrier' => is_scalar($body->Carrier) ? $body->Carrier : '',
            'service' => is_scalar($body->Service) ? $body->Service : '',
            'trackingNumber' => is_scalar($body->TrackingNumber) ? $body->TrackingNumber : '',
        ];
    }

    /**
     * Find the order model given the OrderNumber passed to us from ShipStation.
     *
     * Note: the OrderNumber value from ShipStation corresponds to $order->number that we
     *       return to ShipStation as part of the getOrders() method above.
     *
     * @param object $body
     * @throws HttpException, 404 if not found, 406 if order number is invalid
     * @return Commerce_Order
     */
    protected function orderFromBody(object $body)
    {
        if (isset($body->OrderNumber)) {
            $findOrderEvent = new FindOrderEvent(['orderNumber' => $body->OrderNumber]);
            Event::trigger(static::class, self::FIND_ORDER_EVENT, $findOrderEvent);

            $order = $findOrderEvent->order;
            if (!$order) {
                if ($order = Order::find()->reference($body->OrderNumber)->one()) {
                    return $order;
                }

                throw new HttpException(404, "Order with number '{$body->OrderNumber}' not found");
            }

            return $order;
        }
        throw new HttpException(406, 'Order number must be set');
    }

    /**
     * Responds to the request with XML.
     *
     * See craft/app/controllers/BaseController.php#returnJson() for comparisons
     *
     * @param SimpleXMLElement $xml
     * @return null
     */
    protected function returnXML(SimpleXMLElement $xml)
    {
        header("Content-type: text/xml");
        // Output it into a buffer, in case TasksService wants to close the connection prematurely
        ob_start();
        echo $xml->asXML();

        exit(0);
    }

    /**
     * Returns whether line item status should use shipped or partiallyShipped status setting
     *
     * @param LineItem $lineItem
     * @param int $newQty
     * @param int|null $shippedQty
     * @return string
     */
    protected function getNewLineItemStatus(LineItem $lineItem, int $newQty, ?int $shippedQty)
    {
        if ($shippedQty + $newQty === $lineItem->qty) {
            return 'shipped';
        }

        return 'partiallyShipped';
    }

    /**
     * Returns an array of all ShipmentRecords related to the order, keyed by line item ID
     *
     * @param int $orderId
     * @return array
     */
    protected function getShippedQtys(int $orderId)
    {
        $records = ShipmentRecord::find()
            ->where(['orderId' => $orderId])
            ->all();

        $qtys = [];
        foreach ($records as $record) {
            $qtys = array_merge_recursive($qtys, json_decode($record->shippedQtys, true));
        }

        return array_map([$this, 'sumQtys'], $qtys);
    }

    /**
     * Returns sum of array, otherwise typecast int
     *
     * @param mixed $value
     * @return int
     */
    protected function sumQtys($val)
    {
        if (gettype($val) === 'array') {
            return array_sum($val);
        }

        return (int)$val;
    }
}
