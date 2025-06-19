<?php

declare(strict_types=1);

namespace StaffCore\form;

use jojoe77777\FormAPI\SimpleForm;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\player\Player;

class InvSeeChooserForm extends SimpleForm {

    public function __construct(private Player $target) {
        parent::__construct(function(Player $player, ?int $data) {
            if ($data === null) return;
            
            // Comprobar si InvMenu está disponible
            if (!class_exists(InvMenu::class)) {
                $player->sendMessage("§cLa función de ver inventarios está deshabilitada (Falta InvMenu).");
                return;
            }

            switch ($data) {
                case 0: // Ver inventario
                    $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);
                    $menu->setName($this->target->getName() . "'s Inventory");
                    $menu->getInventory()->setContents($this->target->getInventory()->getContents());
                    $menu->getInventory()->setContents($this->target->getArmorInventory()->getContents());
                    $menu->setListener(InvMenu::readonly());
                    $menu->send($player);
                    break;
                case 1: // Ver Ender Chest
                    $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);
                    $menu->setName($this->target->getName() . "'s Ender Chest");
                    $menu->getInventory()->setContents($this->target->getEnderChestInventory()->getContents());
                    $menu->setListener(InvMenu::readonly());
                    $menu->send($player);
                    break;
            }
        });
        
        $this->setTitle("§8Inspector de " . $this->target->getName());
        $this->setContent("¿Qué inventario deseas ver?");
        $this->addButton("Inventario Principal", SimpleForm::IMAGE_TYPE_PATH, "textures/items/chest.png");
        $this->addButton("Ender Chest", SimpleForm::IMAGE_TYPE_PATH, "textures/items/ender_chest.png");
    }
}
