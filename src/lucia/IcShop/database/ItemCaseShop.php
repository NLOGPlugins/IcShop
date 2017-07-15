<?php

namespace lucia\IcShop\database;

use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\entity\Entity;
use pocketmine\Player;
use pocketmine\network\mcpe\protocol\AddItemEntityPacket;
use pocketmine\network\mcpe\protocol\RemoveEntityPacket;

class ItemCaseShop extends Position {
	public $item;
	public $sellprice, $buyprice;
	private $pos;
	private $eid;
	public function __construct(Item $item, Position $pos, $buyprice, $sellprice) {
		$this->item = $item;
		$this->sellprice = $sellprice;
		$this->buyprice = $buyprice;
		$this->sellprice = $sellprice;
		$this->eid = Entity::$entityCount ++;
		$this->pos = $pos;
		parent::__construct ( $pos->x, $pos->y, $pos->z, $pos->level );
	}
	public function removeFrom(Player $p) {
		$pk = new RemoveEntityPacket();
		$pk->entityUniqueId = $this->eid;
		$p->dataPacket ( $pk );
	}
	public function spawnTo(Player $p) {
		$pk = new AddItemEntityPacket();
		$pk->x = $this->x + 0.5;
		$pk->y = $this->y + 1;
		$pk->z = $this->z + 0.5;
		$pk->entityRuntimeId = $this->eid;
		$pk->item = $this->item;
		$p->dataPacket ( $pk );
	}
}
