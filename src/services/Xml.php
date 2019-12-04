<?php
namespace fostercommerce\shipstationconnect\services;

use fostercommerce\shipstationconnect\Plugin;
use fostercommerce\shipstationconnect\events\OrderFieldEvent;
use craft\commerce\Plugin as CommercePlugin;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\Customer;
use craft\commerce\models\Address;
use yii\base\Component;
use yii\base\Event;

class Xml extends Component
{
    const LINEITEM_OPTION_LIMIT = 10;
    const ORDER_FIELD_EVENT = 'orderFieldEvent';

    public function shouldInclude($order)
    {
        $settings = Plugin::getInstance()->settings;
        $billingSameAsShipping = $settings->billingSameAsShipping;
        return $order->getShippingAddress() && ($billingSameAsShipping || $order->getBillingAddress());
    }

    /**
     * Build an XML document given an array of orders
     *
     * @param SimpleXMLElement $xml the xml to add a child to or modify
     * @param [Order] $orders
     * @param String $name the name of the child node, default 'Orders'
     * @return SimpleXMLElement
     */
    public function orders(\SimpleXMLElement $xml, $orders, $name='Orders')
    {
        $orders_xml = $xml->getName() == $name ? $xml : $xml->addChild($name);
        foreach ($orders as $order) {
            if ($this->shouldInclude($order)) {
                $this->order($orders_xml, $order);
            }
        }

        return $xml;
    }

    /**
     * Build an XML document given a Order instance
     *
     * @param SimpleXMLElement $xml the xml to add a child to or modify
     * @param Order $order
     * @param String $name the name of the child node, default 'Order'
     * @return SimpleXMLElement
     */
    public function order(\SimpleXMLElement $xml, Order $order, $name='Order')
    {
        $order_xml = $xml->getName() == $name ? $xml : $xml->addChild($name);

        $order_mapping = [
            'OrderID' => [
                'callback' => function ($order) {
                    $settings =  Plugin::getInstance()->settings;
                    $prefix = $settings->orderIdPrefix;
                    return $prefix . $order->id;
                },
                'cdata' => false,
            ],
            'OrderNumber' => [
                'callback' => function ($order) {
                    $orderFieldEvent = new OrderFieldEvent([
                        'field' => OrderFieldEvent::FIELD_ORDER_NUMBER,
                        'order' => $order,
                        'value' => $order->reference,
                    ]);

                    Event::trigger(static::class, self::ORDER_FIELD_EVENT, $orderFieldEvent);
                    return $orderFieldEvent->value;
                },
                'cdata' => false,
            ],
            'OrderStatus' => [
                'callback' => function ($order) {
                    return $order->getOrderStatus()->handle;
                },
                'cdata' => false,
            ],
            'OrderTotal' => [
                'callback' => function ($order) {
                    return round($order->totalPrice, 2);
                },
                'cdata' => false,
            ],
            'TaxAmount' => [
                'callback' => function ($order) {
                    return $order->getTotalTax();
                },
                'cdata' => false,
            ],
            'ShippingAmount' => [
                'callback' => function ($order) {
                    return $order->getTotalShippingCost();
                },
                'cdata' => false,
            ]
        ];
        $this->mapCraftModel($order_xml, $order_mapping, $order);

        $order_xml->addChild('OrderDate', date_format($order->dateOrdered ?: $order->dateCreated, 'n/j/Y H:m'));

        $order_xml->addChild('LastModified', date_format($order->dateUpdated ?: $order->dateCreated, 'n/j/Y H:m'));

        $this->shippingMethod($order_xml, $order);

        $paymentSource = $order->getPaymentSource();
        if ($paymentSource) {
            $this->addChildWithCDATA($order_xml, 'PaymentMethod', $paymentSource->description);
        }

        $items_xml = $this->items($order_xml, $order->getLineItems());
        $this->discount($items_xml, $order);

        $customer = $order->getCustomer();
        $customer_xml = $this->customer($order_xml, $customer);

        $billTo_xml = $this->billTo($customer_xml, $order, $customer);

        $shipTo_xml = $this->shipTo($customer_xml, $order, $customer);

        $this->customOrderFields($order_xml, $order);

        return $order_xml;
    }

    /**
     * Add a child with the shipping method to the order_xml, allowing plugins to override as needed
     *
     * @param SimpleXMLElement $order_xml the order xml to add a child to
     * @param [Order] $order
     * @return null
     */
    public function shippingMethod(\SimpleXMLElement $order_xml, $order)
    {
        $orderFieldEvent = new OrderFieldEvent([
            'field' => OrderFieldEvent::FIELD_SHIPPING_METHOD,
            'order' => $order,
        ]);
        Event::trigger(static::class, self::ORDER_FIELD_EVENT, $orderFieldEvent);

        if (!$orderFieldEvent->value && $shippingMethod = $order->getShippingMethod()) {
            $orderFieldEvent->value = $shippingMethod->handle;
        }

        $this->addChildWithCDATA($order_xml, 'ShippingMethod', $orderFieldEvent->value);
    }

    /**
     * Build an XML document given an array of items
     *
     * @param SimpleXMLElement $xml the xml to add a child to or modify
     * @param [LineItem] $items
     * @param String $name the name of the child node, default 'Items'
     * @return SimpleXMLElement
     */
    public function items(\SimpleXMLElement $xml, $items, $name='Items')
    {
        $items_xml = $xml->getName() == $name ? $xml : $xml->addChild($name);
        foreach ($items as $item) {
            $this->item($items_xml, $item);
        }

        return $items_xml;
    }

    /**
     * Build an XML document given a LineItem instance
     *
     * @param SimpleXMLElement $xml the xml to add a child to or modify
     * @param LineItem $item
     * @param String $name the name of the child node, default 'Item'
     * @return SimpleXMLElement
     */
    public function item(\SimpleXMLElement $xml, LineItem $item, $name='Item')
    {
        $item_xml = $xml->getName() == $name ? $xml : $xml->addChild($name);

        $item_mapping = [
            'SKU' => [
                'callback' => function ($item) {
                    return $item->snapshot['sku'];
                }
            ],
            'Name' => 'description',
            'Weight' => [
                'callback' => function ($item) {
                    $weight_units = CommercePlugin::getInstance()->settings->weightUnits;

                    if ($weight_units == 'kg') {
                        // kilograms need to be converted to grams for ShipStation
                        return round($item->weight * 1000, 2);
                    }

                    return round($item->weight, 2);
                },
                'cdata' => false,
            ],
            'Quantity' => [
                'field' => 'qty',
                'cdata' => false,
            ],
            'UnitPrice' => [
                'callback' => function ($item) {
                    return round($item->salePrice, 2);
                },
                'cdata' => false,
            ]
        ];
        $this->mapCraftModel($item_xml, $item_mapping, $item);

        switch (CommercePlugin::getInstance()->settings->weightUnits) {
            case 'lb':
                $ss_weight_units = 'Pounds';
                break;
            case 'kg':
            case 'g':
            default:
                $ss_weight_units = 'Grams';
        }

        $item_xml->addChild('WeightUnits', $ss_weight_units);

        if (isset($item->options)) {
            $option_xml = $this->options($item_xml, $item->options);
        }

        return $item_xml;
    }

    /**
     * Discounts (a.k.a. coupons) are added as items
     * @param  SimpleXMLElement      $xml  [description]
     * @param  Order $order [description]
     * @param  string                 $name [description]
     * @return [type]                       [description]
     */
    public function discount(\SimpleXMLElement $xml, Order $order, $name='Item')
    {
        // If no discount was applied, skip this
        if ($order->getTotalDiscount() >= 0) {
            return;
        }

        $discount_xml = $xml->getName() == $name ? $xml : $xml->addChild($name);

        $discount_mapping = [
            'SKU' => [
                'callback' => function ($order) {
                    return '';
                },
                'cdata' => false
            ],
            'Name' => 'couponCode',
            'Quantity' => [
                'callback' => function ($order) {
                    return 1;
                },
                'cdata' => false
            ],
            'UnitPrice'  => [
                'callback' => function ($order) {
                    return number_format($order->getTotalDiscount(), 2);
                },
                'cdata' => false,
            ],
            'Adjustment' => [
                'callback' => function ($order) {
                    return 'true';
                },
                'cdata' => false,
            ],
        ];
        $this->mapCraftModel($discount_xml, $discount_mapping, $order);

        return $discount_xml;
    }

    /**
     * Build an XML document given a hash of options
     *
     * @param SimpleXMLElement $xml the xml to add a child to or modify
     * @param array $options
     * @param String $name the name of the child node, default 'Options'
     * @return SimpleXMLElement
     */
    public function options(\SimpleXMLElement $xml, $options, $name='Options')
    {
        $options_xml = $xml->getName() == $name ? $xml : $xml->addChild($name);


        $index = 0;
        foreach ($options as $key => $value) {
            $option_xml = $options_xml->addChild('Option');
            $option_xml->addChild('Name', $key);

            if (is_array($value) || !is_object($value)) {
                $value = json_encode($value);
            }

            $this->addChildWithCDATA($option_xml, 'Value', $value);

            // ShipStation limits the number of options on any line item
            $index++;
            if ($index === self::LINEITEM_OPTION_LIMIT) {
                break;
            }
        }

        return $xml;
    }

    /**
     * Build an XML document given a Customer instance
     *
     * @param SimpleXMLElement $xml the xml to add a child to or modify
     * @param Customer $customer
     * @param String $name the name of the child node, default 'Customer'
     * @return SimpleXMLElement
     */
    public function customer(\SimpleXMLElement $xml, Customer $customer, $name='Customer')
    {
        $customer_xml = $xml->getName() == $name ? $xml : $xml->addChild($name);

        $customer_mapping = ['CustomerCode' => 'id'];
        $this->mapCraftModel($customer_xml, $customer_mapping, $customer);

        return $customer_xml;
    }

    /**
     * Add a BillTo address XML Child
     *
     * @param SimpleXMLElement $customer_xml the xml to add a child to or modify
     * @param Order $order
     * @param Customer $customer
     * @return SimpleXMLElement, or null if no address exists
     */
    public function billTo(\SimpleXMLElement $customer_xml, Order $order, Customer $customer)
    {
        $billingAddress = $order->getBillingAddress();
        if (!$billingAddress) {
            $settings = Plugin::getInstance()->settings;
            $billingSameAsShipping = $settings->billingSameAsShipping;
            if ($billingSameAsShipping) {
                $billingAddress = $order->getShippingAddress();
            }
        }

        if ($billingAddress) {
            $billTo_xml = $this->address($customer_xml, $billingAddress, 'BillTo');
            if ($billingAddress->firstName && $billingAddress->lastName) {
                $name = "{$billingAddress->firstName} {$billingAddress->lastName}";
            } else {
                $user = $customer->getUser();
                $name = ($user->firstName && $user->lastName) ? "{$user->firstName} {$user->lastName}" : 'unknown';
            }
            $this->addChildWithCDATA($billTo_xml, 'Name', $name);
            $billTo_xml->addChild('Email', $order->email);

            return $billTo_xml;
        }
        return null;
    }

    /**
     * Add a ShipTo address XML Child
     *
     * @param SimpleXMLElement $customer_xml the xml to add a child to or modify
     * @param Order $order
     * @param Customer $customer
     * @return SimpleXMLElement, or null if no address exists
     */
    public function shipTo(\SimpleXMLElement $customer_xml, Order $order, Customer $customer)
    {
        $shippingAddress = $order->getShippingAddress();
        $shipTo_xml = $this->address($customer_xml, $shippingAddress, 'ShipTo');
        if ($shippingAddress->firstName && $shippingAddress->lastName) {
            $name = "{$shippingAddress->firstName} {$shippingAddress->lastName}";
        } else {
            $user = $customer->getUser();
            $name = ($user->firstName && $user->lastName) ? "{$user->firstName} {$user->lastName}" : 'unknown';
        }
        $this->addChildWithCDATA($shipTo_xml, 'Name', $name);

        return $shipTo_xml;
    }

    /**
     * Build an XML document given a Address instance
     *
     * @param SimpleXMLElement $xml the xml to add a child to or modify
     * @param Address $address
     * @param String $name the name of the child node, default 'Address'
     * @return SimpleXMLElement
     */
    public function address(\SimpleXMLElement $xml, Address $address=null, $name='Address')
    {
        $address_xml = $xml->getName() == $name ? $xml : $xml->addChild($name);

        if (!is_null($address)) {
            $address_mapping = [
                'Company' => 'businessName',
                'Phone' => 'phone',
                'Address1' => 'address1',
                'Address2' => 'address2',
                'City' => 'city',
                'State' => 'stateText',
                'PostalCode' => 'zipCode',
                'Country' =>  [
                    'callback' => function ($address) {
                        return $address->countryId ? $address->getCountry()->iso : null;
                    },
                    'cdata' => false,
                ]
            ];
            $this->mapCraftModel($address_xml, $address_mapping, $address);
        }

        return $address_xml;
    }

    /**
     * Allow plugins to add custom fields to the order
     *
     * @param SimpleXMLElement $xml the order xml to add a child
     * @param Order $order
     * @return SimpleXMLElement
     */
    public function customOrderFields(\SimpleXMLElement $order_xml, Order $order)
    {
        $customFields = [
            OrderFieldEvent::FIELD_CUSTOM_FIELD_1,
            OrderFieldEvent::FIELD_CUSTOM_FIELD_2,
            OrderFieldEvent::FIELD_CUSTOM_FIELD_3,
            OrderFieldEvent::FIELD_INTERNAL_NOTES,
            OrderFieldEvent::FIELD_CUSTOMER_NOTES,
            OrderFieldEvent::FIELD_GIFT,
            OrderFieldEvent::FIELD_GIFT_MESSAGE,
        ];

        foreach ($customFields as $fieldName) {
            $orderFieldEvent = new OrderFieldEvent([
                'field' => $fieldName,
                'order' => $order,
            ]);

            Event::trigger(static::class, self::ORDER_FIELD_EVENT, $orderFieldEvent);
            $data = $orderFieldEvent->value ?: '';

            // Gift field requires a boolean value
            if ($fieldName === OrderFieldEvent::FIELD_GIFT) {
                $data = $data ? 'true' : 'false';
                $order_xml->addChild($fieldName, $data);
            } else {
                if ($orderFieldEvent->cdata) {
                    $this->addChildWithCDATA($order_xml, $fieldName, substr(htmlspecialchars($data), 0, 100));
                } else {
                    $order_xml->addChild($fieldName, substr(htmlspecialchars($data), 0, 100));
                }
            }
        }

        return $order_xml;
    }

    /***************************** helpers *******************************/

    protected function mapCraftModel($xml, $mapping, $model)
    {
        foreach ($mapping as $name => $attr) {
            $value = $this->valueFromMappingAndModel($attr, $model);

            //wrap in cdata unless explicitly set not to
            if (!is_array($attr) || !array_key_exists('cdata', $attr) || $attr['cdata']) {
                $this->addChildWithCDATA($xml, $name, $value);
            } else {
                $xml->addChild($name, $value);
            }
        }
        return $xml;
    }

    /**
     * Retrieve data from a Craft model by field name or method name.
     *
     * Example usage:
     *   by field:
     *   $value = $this->valueFromMappingAndModel('id', $order);
     *   echo $value; // order id, wrapped in CDATA tag
     *
     *   by field with custom options
     *   $options = ['field' => 'totalAmount', 'cdata' => false];
     *   $value = $this->valueFromMappingAndModel($options, $value);
     *   echo $value; // the order's totalAmount, NOT wrapped in cdata
     *
     *   by annonymous function (closure):
     *   $value = $this->valueFromMappingAndModel(function($order) {
     *      return is_null($order->name) ? 'N/A' : $order->name;
     *   }, $order);
     *   echo $value; // the order's name if it is set, or 'N/A' otherwise
     *
     *   @param mixed $options, a string field name or
     *                          a callback accepting the model instance as its only parameter or
     *                          a hash containing options with a 'field' or 'callback' key
     *   @param BaseModel $model, an instance of a craft model
     *   @return string
     */
    protected function valueFromMappingAndModel($options, $model)
    {
        $value = null;

        //if field name exists in the options array
        if (is_array($options) && array_key_exists('field', $options)) {
            $field = $options['field'];
            $value = $model->{$field};
        }
        //if value is coming from a callback in the options array
        elseif (is_array($options) && array_key_exists('callback', $options)) {
            $callback = $options['callback'];
            $value = $callback($model);
        }
        //if value is a callback
        elseif (is_object($options) && is_callable($options)) {
            $value = $options($model);
        }
        //if value is an attribute on the model, passed as a string field name
        elseif (is_string($options)) {
            $value = $model->{$options};
        }
        // if null, leave blank
        elseif (is_null($options)) {
            $value = '';
        }

        if ($value === true || $value === false) {
            $value = $value ? "true" : "false";
        }
        return $value;
    }

    /**
     * Add a child with <!CDATA[...]]>
     *
     * We cannot simply do this by manipulating the string, because SimpleXMLElement and/or Craft will encode it
     *
     * @param $xml SimpleXMLElement the parent to which we're adding a child
     * @param $name String the xml node name
     * @param $value Mixed the value of the new child node, which will be wrapped in CDATA
     * @return SimpleXMLElement, the new child
     */
    protected function addChildWithCDATA(&$xml, $name, $value)
    {
        $new_child = $xml->addChild($name);
        if ($new_child !== null) {
            $node = dom_import_simplexml($new_child);
            $node->appendChild($node->ownerDocument->createCDATASection($value));
        }
        return $new_child;
    }
}
