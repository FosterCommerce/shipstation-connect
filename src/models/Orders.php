<?php

namespace fostercommerce\shipstationconnect\models;

use craft\base\Model;
use Illuminate\Support\Collection;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\XmlAttribute;
use JMS\Serializer\Annotation\XmlList;
use JMS\Serializer\Annotation\XmlRoot;

#[XmlRoot('Orders')]
class Orders extends Model
{
	#[Groups(['export'])]
	#[XmlAttribute]
	public int $pages;

	/**
	 * @var Order[]
	 */
	#[Groups(['export'])]
	#[XmlList(inline: true, entry: 'Order')]
	public array $orders = [];

	/**
	 * @param Collection<int, Order> $orders
	 */
	public static function fromCollection(Collection $orders, int $pages): self
	{
		return new self([
			'pages' => $pages,
			'orders' => $orders,
		]);
	}
}
