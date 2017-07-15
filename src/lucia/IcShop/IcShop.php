<?php

namespace lucia\IcShop;

use lucia\IcShop\database\DataBase;
use lucia\IcShop\database\ItemCaseShop;
use onebone\economyapi\EconomyAPI;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\level\Position;

class IcShop extends PluginBase implements Listener {
	const TAG = TextFormat::AQUA . TextFormat::ITALIC . "[ IcShop ] " . TextFormat::WHITE;
	private $db;
	private $shops = [ ];
	private $createqueue = [ ], $removequeue = [ ];
	private $shopqueue = [ ];
	public function onEnable() {
		@mkdir ( $this->getDataFolder () );
		$this->db = new DataBase ( new Config ( $this->getDataFolder () . "data.yml", Config::YAML ) );
		$this->getServer ()->getPluginManager ()->registerEvents ( $this, $this );
		foreach ( $this->db->getAllShops () as $shop ) {
			$pos = "$shop->x:$shop->y:$shop->z:{$shop->getLevel()->getName()}";
			$this->shops [$pos] = $shop;
		}
	}
	public function onJoin(\pocketmine\event\player\PlayerJoinEvent $event) {
		$player = $event->getPlayer ();
		$name = $player->getName ();
		foreach ( $this->shops as $pos => $shop ) {
			if (! $shop instanceof ItemCaseShop) {
				continue;
			}
			if ($shop->getLevel ()->getName () === $player->getLevel ()->getName ()) {
				$shop->spawnTo ( $player );
			}
		}
	}
	public function onTeleport(EntityTeleportEvent $event) {
		$ent = $event->getEntity ();
		if (! $ent instanceof Player)
			return;
		if ($event->getTo ()->getLevel ()->getName () === $event->getFrom ()->getLevel ()->getName ()) {
			return true;
		}
		foreach ( $this->shops as $postr => $shop ) {
			if (! $shop instanceof ItemCaseShop) {
				$this->getServer ()->getLogger ()->info ( "디버그 : ItemCaseShop의 객체가 아님 (EntityTeleportEvent에서 발생)" );
				continue;
			}
			if ($shop->distance ( $ent ) < 10 && $shop->getLevel ()->getName () === $event->getTo ()->getLevel ()->getName ()) {
				$shop->spawnTo ( $ent );
			}
		}
	}
	public function onBlockBreak(BlockBreakEvent $event) {
		$pos = $event->getBlock ();
		$pos = new Position ( $pos->x, $pos->y, $pos->z, $event->getPlayer ()->getLevel () );
		$postr = $this->db->posToStr ( $pos );
		if (isset ( $this->shops [$postr] )) {
			$event->setCancelled ();
			return false;
		}
	}
	private $spawnqueue = [ ];
	public function onMove(PlayerMoveEvent $event) {
		$player = $event->getPlayer ();
		$name = $player->getName ();
		if (! isset ( $this->spawnqueue [$name] )) {
			$this->spawnqueue [$name] = time ();
		}
		foreach ( $this->shops as $postr => $shop ) {
			if (! $shop instanceof ItemCaseShop) {
				$this->getServer ()->getLogger ()->info ( "디버그 : ItemCaseShop의 객체가 아님 (PlayerMoveEvent에서 발생)" );
				continue;
			}
			if ($shop->distance ( $player ) < 10 && $shop->getLevel ()->getName () === $player->getLevel ()->getName () && time () - $this->spawnqueue [$name] >= 30) {
				$shop->spawnTo ( $player );
				$this->spawnqueue [$name] = time ();
			}
		}
	}
	public function onInteract(PlayerInteractEvent $event) {
		$player = $event->getPlayer ();
		$name = $player->getName ();
		if (isset ( $this->createqueue [$name] )) {
			$pos = $event->getBlock ()->add ( 0, 1, 0 );
			$pos = new Position ( $pos->x, $pos->y, $pos->z, $player->getLevel () );
			$postr = $this->db->posToStr ( $pos );
			if (isset ( $this->shops [$postr] )) {
				$player->sendMessage ( self::TAG . "상점을 생성할 수 없습니다 : 다른 상점과 겹칩니다." );
				return true;
			}
			$buyprice = $this->createqueue [$name] [0];
			$sellprice = $this->createqueue [$name] [1];
			$item = $player->getInventory ()->getItemInHand ();
			$item->setCount ( 1 );
			$shop = new ItemCaseShop ( $item, $pos, $buyprice, $sellprice );
			$this->db->writeShop ( $shop );
			$this->shops [$postr] = $shop;
			$event->setCancelled ();
			$player->getLevel ()->setBlock ( $pos, Block::get ( 44 ) );
			$player->sendMessage ( self::TAG . "상점이 생성되었습니다." );
			foreach ( $pos->level->getPlayers () as $p ) {
				if ($p->distance ( $shop ) < 10 && $p->getLevel ()->getName () === $player->getLevel ()->getName ()) {
					$shop->spawnTo ( $p );
				}
			}
			unset ( $this->createqueue [$name] );
		} elseif (isset ( $this->removequeue [$name] )) {
			$pos = $event->getBlock ();
			$pos = new Position ( $pos->x, $pos->y, $pos->z, $player->getLevel () );
			$postr = $this->db->posToStr ( $pos );
			if (! isset ( $this->shops [$postr] )) {
				$player->sendMessage ( self::TAG . "상점을 제거할 수 없습니다 : 상점이 존재하지 않는 좌표입니다." );
				unset ( $this->removequeue [$name] );
				return true;
			}
			$event->setCancelled ();
			$shop = $this->shops [$postr];
			if (! $shop instanceof ItemCaseShop) {
				$this->getServer ()->getLogger ()->info ( '디버그 : ItemCaseShop의 객체가 아님 (PlayerInteractEvent에서 발생)' );
				unset ( $this->removequeue [$name] );
				return true;
			}
			$this->db->deleteShop ( $shop );
			unset ( $this->shops [$postr] );
			foreach ( $shop->level->getPlayers () as $p ) {
				$shop->removeFrom ( $p );
			}
			unset ( $this->removequeue [$name] );
			$player->sendMessage ( self::TAG . "상점이 삭제되었습니다." );
		} else {
			$pos = $event->getBlock ();
			$pos = new Position ( $pos->x, $pos->y, $pos->z, $player->getLevel () );
			$postr = $this->db->posToStr ( $pos );
			if (isset ( $this->shops [$postr] )) {
				$shop = $this->shops [$postr];
				if (! $shop instanceof ItemCaseShop) {
					$this->getServer ()->getLogger ()->info ( '디버그 : ItemCaseShop의 객체가 아님 (PlayerInteractEvent에서 발생)' );
					return true;
				}
				$have = 0;
				foreach ( $player->getInventory ()->getContents () as $item ) {
					if ($shop->item->getId () === $item->getId () && $shop->item->getDamage () === $item->getDamage ()) {
						$have += $item->getCount ();
					}
				}
				$player->sendMessage ( self::TAG . $shop->item->getName () . " : 구매 또는 판매하시겠습니까? {$have}개 보유" );
				$y = TextFormat::YELLOW;
				$w = TextFormat::WHITE;
				$player->sendMessage ( self::TAG . "구매 가격 : $y" . $shop->buyprice . $w . ", 판매 가격 : $y" . $shop->sellprice );
				$player->sendMessage ( self::TAG . "$y/구매 <수량>$w 또는 $y/판매 <수량>$w 명령어를 입력하세요" );
				$event->setCancelled ();
				$this->shopqueue [$player->getName ()] = $shop;
			}
		}
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if ($command->getName () === "판매") {
			$name = $sender->getName ();
			if (! $sender instanceof Player) {
				$sender->sendMessage ( self::TAG . "콘솔에서는 사용하실 수 없는 명령어입니다." );
				return true;
			}
			if (! isset ( $this->shopqueue [$name] )) {
				$sender->sendMessage ( self::TAG . "아이템을 판매하시려면 먼저 상점을 터치해 주세요." );
				return true;
			}
			$shop = $this->shopqueue [$name];
			if (! $shop instanceof ItemCaseShop) {
				$this->getServer ()->getLogger ()->info ( "디버그 : ItemCaseShop의 객체가 아님 (onCommand에서 발생)" );
				return true;
			}
			if (! isset ( $args [0] ) || ! is_numeric ( $args [0] )) {
				$sender->sendMessage ( self::TAG . "사용법: /판매 <수량>" );
				return true;
			}
			$count = ( int ) $args [0];
			$sellprice = $shop->sellprice;
			$total = $count * $sellprice;
			$item = clone $shop->item;
			$item->setCount ( $count );
			
			if (! $sender->getInventory ()->contains ( $item )) {
				$sender->sendMessage ( self::TAG . "아이템이 부족합니다." );
				return true;
			}
			if ($count <= 0) {
				$sender->sendMessage ( self::TAG . "올바른 숫자를 입력해주세요" );
				return true;
			}
			EconomyAPI::getInstance ()->addMoney ( $sender, $total );
			$sender->sendMessage ( self::TAG . $item->getName () . "을 판매하셨습니다 : {$item->getCount()}개" );
			$sender->getInventory ()->removeItem ( $item );
			unset ( $this->shopqueue [$name] );
			return true;
		}
		if ($command->getName () === "구매") {
			$name = $sender->getName ();
			if (! $sender instanceof Player) {
				$sender->sendMessage ( self::TAG . "콘솔에서는 사용하실 수 없는 명령어입니다." );
				return true;
			}
			if (! isset ( $this->shopqueue [$name] )) {
				$sender->sendMessage ( self::TAG . "아이템을 구매하시려면 먼저 상점을 터치해 주세요." );
				return true;
			}
			$shop = $this->shopqueue [$name];
			if (! $shop instanceof ItemCaseShop) {
				$this->getServer ()->getLogger ()->info ( "디버그 : ItemCaseShop의 객체가 아님 (onCommand에서 발생)" );
				return true;
			}
			if (! isset ( $args [0] ) || ! is_numeric ( $args [0] )) {
				$sender->sendMessage ( self::TAG . "사용법: /구매 <수량>" );
				return true;
			}
			$count = ( int ) $args [0];
			$buyprice = $shop->buyprice;
			$total = $count * $buyprice;
			$item = clone $shop->item;
			$item->setCount ( $args [0] );
			if ($count <= 0) {
				$sender->sendMessage ( self::TAG . "올바른 숫자를 입력해주세요" );
				return true;
			}
			if (($mm = EconomyAPI::getInstance ()->myMoney ( $sender )) < $total) {
				$m = $buyprice - $mm;
				$sender->sendMessage ( self::TAG . "아이템 구매에 필요한 돈이 부족합니다 : " . "$m$ 부족" );
				return true;
			}
			EconomyAPI::getInstance ()->reduceMoney ( $sender, $total );
			$sender->sendMessage ( self::TAG . $item->getName () . " 을 구매하셨습니다 : {$item->getCount()}개" );
			$sender->getInventory ()->addItem ( $item );
			unset ( $this->shopqueue [$name] );
			return true;
		}
		if ($command->getName () === "상점") {
			$name = $sender->getName ();
			if (! $sender instanceof Player) {
				$sender->sendMessage ( self::TAG . "콘솔에서는 사용하실 수 없는 명령어입니다." );
				return true;
			}
			if (! isset ( $args [0] )) {
				$sender->sendMessage ( self::TAG . "사용법: /상점 <생성> <제거>" );
				return true;
			}
			switch ($args [0]) {
				case "생성" :
				case 'c' :
				case "create" :
					if (! isset ( $args [1] ) || ! isset ( $args [2] ) || ! is_numeric ( $args [1] ) || ! is_numeric ( $args [2] )) {
						$sender->sendMessage ( self::TAG . "사용법: /상점 <생성> <구매가격> <판매가격>" );
						return true;
					}
					$sender->sendMessage ( self::TAG . "상점을 생성합니다. 생성할 상점의 위치를 터치해주세요." );
					$this->createqueue [$name] = [ 
							$args [1],
							$args [2] 
					];
					break;
				case "제거" :
				case "remove" :
				case "r" :
					$sender->sendMessage ( self::TAG . "상점을 제거합니다. 제거할 상점을 터치해주세요." );
					$this->removequeue [$name] = true;
					break;
				default :
					$sender->sendMessage ( self::TAG . "사용법: /상점 <생성> <제거>" );
			}
			return true;
		}
	}
}
