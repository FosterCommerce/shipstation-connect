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
	private int $pages;

	/**
	 * @var Order[]
	 */
	#[Groups(['export'])]
	#[XmlList(inline: true, entry: 'Order')]
	private array $orders = [];

	public function getPages(): int
	{
		return $this->pages;
	}

	public function setPages(int $pages): void
	{
		$this->pages = $pages;
	}

	/**
	 * @return Order[]
	 */
	public function getOrders(): array
	{
		return $this->orders;
	}

	/**
	 * @param Order[] $orders
	 */
	public function setOrders(array $orders): void
	{
		$this->orders = $orders;
	}

	/**
	 * @param Collection<int, Order> $orders
	 */
	public static function fromCollection(Collection $orders, int $pages): self
	{
		return new self([
			'pages' => $pages,
			'orders' => $orders->toArray(),
		]);
	}
}
