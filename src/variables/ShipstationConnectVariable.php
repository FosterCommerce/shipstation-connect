<?php

namespace fostercommerce\shipstationconnect\variables;

use fostercommerce\shipstationconnect\records\Shipment;

class ShipstationConnectVariable
{
    // Public Methods
    // =========================================================================

    public function getShipmentQtys(int $id)
    {
        return json_decode(Shipment::findOne(['shipmentId' => $id])->shippedQtys, true);
    }
}
