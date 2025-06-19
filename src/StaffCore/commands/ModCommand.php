<?php

declare(strict_types=1);

namespace StaffCore\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\ItemFactory;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use StaffCore\Main;
use StaffCore\session\PlayerSession;

class ModCommand extends Command {

    private Main $plugin;

    public function __construct() {
        parent::__construct("mod", "Activa/Desactiva el Modo Staff.", "/mod", ["staff"]);
        $this->setPermission("staffcore.command.mod");
        $this->plugin = Main::getInstance();
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$this->testPermission($sender)) return false;

        if (!$sender instanceof Player) {
            $sender->sendMessage("§cEste comando solo puede ser usado en el juego.");
            return false;
        }

        $session = $this->plugin->getSessionManager()->getSession($sender);
        if ($session === null) return false;

        if ($session->isInStaffMode()) {
            $this->deactivateStaffMode($sender, $session);
        } else {
            $this->activateStaffMode($sender, $session);
        }
        return true;
    }

    private function activateStaffMode(Player $player, PlayerSession $session): void {
        // Guardar estado y inventario
        $session->saveInventory();
        $session->setOriginalNameTag($player->getNameTag());
        $session->setOriginalGamemode($player->getGamemode());

        // Limpiar y preparar al jugador
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEffects()->clear();
        $player->getHungerManager()->setFood(20);
        $player->setHealth($player->getMaxHealth());
        $player->setGamemode(GameMode::CREATIVE());
        $player->setFlying(true);
        $player->setAllowFlight(true);

        $newNameTag = "§c§lStaff Mode\n§r" . $session->getOriginalNameTag();
        $player->setNameTag($newNameTag);

        self::giveStaffItems($player);
        
        $session->setInStaffMode(true);
        if ($this->plugin->getConfig()->getNested("staff_mode.vanish_on_enable", true)) {
            $session->setVanished(true); // El método ahora actualiza la visibilidad
            $player->sendMessage($this->plugin->getMessage("vanish_on"));
        }
        
        $player->sendMessage($this->plugin->getMessage("staff_mode_on"));
    }

    private function deactivateStaffMode(Player $player, PlayerSession $session): void {
        // Restaurar estado e inventario
        $session->restoreInventory();
        $player->setNameTag($session->getOriginalNameTag());
        $player->setGamemode($session->getOriginalGamemode());
        
        // Limpiar estado de staff
        $session->setInStaffMode(false);
        if ($session->isVanished()) {
            $session->setVanished(false); // Asegurarse de que sea visible
        }

        $player->sendMessage($this->plugin->getMessage("staff_mode_off"));
    }

    public static function giveStaffItems(Player $player): void {
        $plugin = Main::getInstance();
        $inv = $player->getInventory();
        $config = $plugin->getConfig()->get("staff_mode.items", []);

        // Teleporter
        $teleporter = VanillaItems::COMPASS()->setCustomName($plugin->getMessage("staff_mode.items.teleporter.name", "§r§aTeletransportador"));
        $teleporter->setLore(explode("\n", $plugin->getMessage("staff_mode.items.teleporter.lore", "")));
        $inv->setItem($config['teleporter']['slot'] ?? 0, $teleporter);

        // Freeze Tool
        $freezeItem = VanillaItems::ICE()->setCustomName($plugin->getMessage("staff_mode.items.freeze.name", "§r§bCongelador"));
        $inv->setItem($config['freeze']['slot'] ?? 1, $freezeItem);

        // Inventory Viewer
        $invSeeItem = VanillaItems::CHEST()->setCustomName($plugin->getMessage("staff_mode.items.inventory_viewer.name", "§r§eInspector de Inventario"));
        $inv->setItem($config['inventory_viewer']['slot'] ?? 2, $invSeeItem);

        // Player Info
        $infoItem = VanillaItems::STICK()->setCustomName($plugin->getMessage("staff_mode.items.player_info.name", "§r§6Info del Jugador"));
        $inv->setItem($config['player_info']['slot'] ?? 3, $infoItem);
        
        // Ban Tool
        $banItem = VanillaItems::BOOK()->setCustomName($plugin->getMessage("staff_mode.items.ban_tool.name", "§r§cHerramienta de Sanción"));
        $banItem->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 1));
        $inv->setItem($config['ban_tool']['slot'] ?? 7, $banItem);

        // Vanish Tool
        $session = $plugin->getSessionManager()->getSession($player);
        $vanishCustomName = ($session !== null && $session->isVanished()) 
            ? $plugin->getMessage("vanish_item_on_name") 
            : $plugin->getMessage("vanish_item_off_name");
        $vanishItem = ItemFactory::getInstance()->get(VanillaItems::LIME_DYE()->getId(), 0, 1)->setCustomName($vanishCustomName);
        $inv->setItem($config['vanish']['slot'] ?? 8, $vanishItem);
    }
}
