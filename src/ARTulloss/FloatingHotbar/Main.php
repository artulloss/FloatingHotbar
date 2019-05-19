<?php

declare(strict_types=1);

namespace ARTulloss\FloatingHotbar;

use ARTulloss\Hotbar\Events\LoseHotbarEvent;
use pocketmine\entity\Entity;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

/**
 *  _  _  __ _____ __  __  ___
 * | || |/__\_   _|  \/  \| _ \
 * | >< | \/ || | | -< /\ | v /
 * |_||_|\__/ |_| |__/_||_|_|_\
 *
 * @author ARTulloss
 * @link https://github.com/artulloss
 */

class Main extends PluginBase implements Listener{

    /** @var \ARTulloss\Hotbar\Main */
    private $hotbar;
    /** @var CustomItem[] */
    private $playerItems;
    /** @var float $heightOffset */
    private $heightOffset;
    /** @var bool $movementTracking */
    private $movementTracking;

	public function onEnable(): void{
	    $this->getServer()->getPluginManager()->registerEvents($this, $this);
        Entity::registerEntity(CustomItem::class, false, ['ItemFloating', 'minecraft:itemfloating']);
        /** @var \ARTulloss\Hotbar\Main $hotbar */
        $this->hotbar = $this->getServer()->getPluginManager()->getPlugin('Hotbar');
        $config = $this->getConfig();
        $this->movementTracking = $config->get('Movement Tracking');
        $this->heightOffset = $config->get('Height Offset');
    }
    /**
     * @param PlayerMoveEvent $event
     */
    public function onMove(PlayerMoveEvent $event): void{
        if($this->movementTracking) {
            $player = $event->getPlayer();
            $name = $player->getName();
            $hotbar = $this->hotbar->getHotbarUsers()->getHotbarFor($player);
            if($hotbar !== null) {
                if(isset($this->playerItems[$name])) {
                    $position = $this->calculateRelativePosition($player);
                    $this->playerItems[$name]->teleport($position);
                }
            }
        }
    }
    /**
     * @param PlayerItemHeldEvent $event
     * @priority HIGHEST
     */
    public function holdItem(PlayerItemHeldEvent $event): void {
        $player = $event->getPlayer();
        $users = $this->hotbar->getHotbarUsers();
        $hotbarUser = $users->getHotbarFor($player);
        if($hotbarUser !== null) {
            $inv = $player->getInventory();
            $index = $inv->getHeldItemIndex();
            $items = $hotbarUser->getHotbar()->getItems();
            $item = $inv->getItem($index);
            if(isset($items[$index + 1]) && ($hotbarItem = $items[$index + 1]) && $item->getName() === $hotbarItem->getName()
                && $item->getId() === $hotbarItem->getId() && $item->getDamage() === $hotbarItem->getDamage()) {
                $name = $player->getName();
                if (isset($this->playerItems[$name])) {
                    $this->safeDespawn($this->playerItems[$name]);
                    unset($this->playerItems[$name]);
                }
                $item = $event->getItem();
                $position = $this->calculateRelativePosition($player);
                if ($item->getId() !== Item::AIR) {
                    $nbt = Entity::createBaseNBT($position, null, lcg_value() * 360, 0);
                    $itemTag = $item->nbtSerialize();
                    $itemTag->setName("Item");
                    $nbt->setShort("Health", 5);
                    $nbt->setShort("PickupDelay", 999);
                    $nbt->setTag($itemTag);
                    $itemEntity = Entity::createEntity("ItemFloating", $player->getLevel(), $nbt, $player);
                    if ($itemEntity instanceof CustomItem) {
                        $itemEntity->spawnTo($player);
                        $itemEntity->entityBaseTick(0);
                        $this->playerItems[$name] = $itemEntity;
                    }
                }
            } elseif($item->getId() !== Item::AIR)
                $users->remove($player, false);
        }
    }
    /**
     * @param Player $player
     * @return Vector3
     */
    private function calculateRelativePosition(Player $player): Vector3{
        $position = $player->asVector3();
        $direction = $player->getDirectionVector();
        $subtract = $direction->multiply(0.75);
        $position = $position->add($subtract);
        $position->y += ($player->getEyeHeight() + $this->heightOffset);
        return $position;
    }
    /**
     * @param LoseHotbarEvent $event
     */
    public function onLoseHotbar(LoseHotbarEvent $event): void{
        $name = $event->getHotbarUser()->getPlayer()->getName();
        if(isset($this->playerItems[$name]))
            $this->safeDespawn($this->playerItems[$name]);
    }
    /**
     * @param ItemEntity $item
     */
    public function safeDespawn(ItemEntity $item): void{
        if (!$item->isClosed())
            $item->flagForDespawn();
    }
}
