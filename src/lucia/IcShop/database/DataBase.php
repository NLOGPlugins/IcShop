<?php

namespace lucia\IcShop\database;

use pocketmine\utils\Config;
use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\Server;

class DataBase {
	private $conf, $db;
	public function __construct(Config $conf) {
		$this->conf = $conf;
		$this->db = $conf->getAll ();
	}
	public function save() {
		$this->conf->setAll ( $this->db );
		$this->conf->save ();
	}
	public function writeShop(ItemCaseShop $shop) {
		$pos = "$shop->x:$shop->y:$shop->z:{$shop->getLevel()->getName()}";
		$this->db [$pos] = [ ];
		$item = "{$shop->item->getId()}:{$shop->item->getDamage()}";
		$this->db [$pos] ["item"] = $item;
		$this->db [$pos] ["buyprice"] = $shop->buyprice;
		$this->db [$pos] ["sellprice"] = $shop->sellprice;
		$this->save ();
	}
	public function getShopExistsByPos(Position $pos) {
		$pos = $this->posToStr ( $pos );
		return isset ( $this->db [$pos] );
	}
	public function deleteShop(ItemCaseShop $shop) {
		$postr = $this->posToStr ( $shop );
		unset ( $this->db [$postr] );
		$this->save ();
	}
	public function posToStr(Position $pos) {
		return "$pos->x:$pos->y:$pos->z:{$pos->level->getName()}";
	}
	public function strToPos($str) {
		$str = explode ( ":", $str );
		return new Position ( $str [0], $str [1], $str [2], Server::getInstance ()->getLevelByName ( $str [3] ) );
	}
	/**
	 *
	 * @return ItemCaseShop[]
	 */
	public function getAllShops() {
		$shops = [ ];
		foreach ( $this->db as $pos => $data ) {
			$itemkey = explode ( ":", $data ["item"] );
			$item = Item::get ( $itemkey [0], $itemkey [1] );
			$pos = $this->strToPos ( $pos );
			$shop = new ItemCaseShop ( $item, $pos, $data ["buyprice"], $data ["sellprice"] );
			array_push ( $shops, $shop );
		}
		return $shops;
	}
}