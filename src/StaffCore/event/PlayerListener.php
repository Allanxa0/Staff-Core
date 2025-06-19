<?php

declare(strict_types=1);

namespace StaffCore\event;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemPickupEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use StaffCore\form\BanForm;
use StaffCore\form\InvSeeChooserForm;
use StaffCore\form\PlayerInfoForm;
use StaffCore\Main;
use StaffCore\session\PlayerSession;

class PlayerListener implements Listener {

    private Main $plugin;

    public function __construct() {
        $this->plugin = Main::getInstance();
    }

    public function onLogin(PlayerLoginEvent $event): void {
        $player = $event->getPlayer();
        $banInfo = $this->plugin->getDatabaseManager()->getBanInfo($player);

        if ($banInfo !== null) {
            $expireDate = $banInfo['expire_date'];
            if ($expireDate === PHP_INT_MAX) {
                $timeFormat = $this->plugin->getMessage("duration_permanent");
            } else {
                $remaining = $expireDate - time();
                $days = floor($remaining / 86400);
                $hours = floor(($remaining % 86400) / 3600);
                $minutes = floor(($remaining % 3600) / 60);
                $timeFormat = ($days > 0 ? "$days d " : "") . ($hours > 0 ? "$hours h " : "") . ($minutes > 0 ? "$minutes m" : "menos de un minuto");
            }

            $kickMessage = $this->plugin->getMessage("ban_kick_message", [
                "reason" => $banInfo['reason'],
                "time" => trim($timeFormat)
            ]);
            $event->setKickMessage($kickMessage);
            $event->cancel();
        }
    }
    
    public function onJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $this->plugin->getSessionManager()->createSession($player);
        $session = $this->plugin->getSessionManager()->getSession($player);

        if ($session === null) return;
        
        // --- Mecanismo de Recuperación ---
        if ($session->isInStaffMode()) {
            $player->sendMessage($this->plugin->getMessage("staff_mode_join_notification"));
            // Forzamos la restauración del modo staff visualmente
            $player->setGamemode(GameMode::CREATIVE());
            $player->setFlying(true);
            $newNameTag = "§c§lStaff Mode\n§r" . $player->getNameTag();
            $player->setNameTag($newNameTag);
            ModCommand::giveStaffItems($player); // Re-entregar items
        } elseif ($player->hasPermission("staffcore.onjoin.message")) {
            $player->sendMessage($this->plugin->getMessage("staff_mode_join_notification"));
        }
        
        if ($session->isFrozen()) {
            $newFreezeNameTag = "§b§lCONGELADO§r\n" . ($session->getPreFreezeNameTag() ?? $player->getNameTag());
            $player->setNameTag($newFreezeNameTag);
            $player->sendTitle($this->plugin->getMessage("freeze_target_message_title"), $this->plugin->getMessage("freeze_target_message_subtitle"));
            $player->sendMessage($this->plugin->getMessage("freeze_chat_header"));
        }

        // --- Lógica de Vanish al Entrar ---
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $onlinePlayer) {
            $onlineSession = $this->plugin->getSessionManager()->getSession($onlinePlayer);
            if ($onlineSession !== null && $onlineSession->isVanished()) {
                if (!$player->hasPermission("staffcore.feature.vanish.see")) {
                    $player->hidePlayer($onlinePlayer);
                }
            }
        }
    }

    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->getSession($player);

        if ($session !== null && $session->isFrozen() && $this->plugin->getConfig()->getNested("freeze_disconnect_ban.enabled", true)) {
            $days = $this->plugin->getConfig()->getNested("freeze_disconnect_ban.days", 30);
            $reason = "Desconexión mientras estaba congelado.";
            
            $this->plugin->getDatabaseManager()->banPlayer(
                $player->getName(),
                $player->getNetworkSession()->getIp(),
                "Servidor (Auto)",
                $reason,
                "IP",
                time() + ($days * 86400)
            );
            
            $broadcast = $this->plugin->getMessage("freeze_disconnect_ban_message", ["player" => $player->getName(), "days" => $days]);
            Server::getInstance()->broadcastMessage($broadcast);
            
             $this->plugin->sendDiscordWebhook("Jugador Baneado (Automático)", "", 0xFF0000, [
                "Jugador" => $player->getName(), "Razón" => $reason, "Duración" => "$days días"
            ]);
        }
        $this->plugin->getSessionManager()->removeSession($player);
    }
    
    public function onMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->getSession($player);

        if ($session !== null && $session->isFrozen()) {
            if($event->getFrom()->floor()->distance($event->getTo()->floor()) > 0.1){
                $event->cancel();
                $player->sendTitle($this->plugin->getMessage("freeze_move_warning_title"), $this->plugin->getMessage("freeze_move_warning_subtitle"));
            }
        }
    }
    
    public function onDamage(EntityDamageEvent $event): void {
        $player = $event->getEntity();
        if (!$player instanceof Player) return;

        $session = $this->plugin->getSessionManager()->getSession($player);
        if ($session !== null && ($session->isInStaffMode() || $session->isFrozen())) {
            $event->cancel();
        }
    }

    public function onDamageByEntity(EntityDamageByEntityEvent $event): void {
        $damager = $event->getDamager();
        $entity = $event->getEntity();

        if (!$damager instanceof Player || !$entity instanceof Player) return;

        $session = $this->plugin->getSessionManager()->getSession($damager);
        if ($session === null || !$session->isInStaffMode()) return;

        $event->cancel();
        $item = $damager->getInventory()->getItemInHand();
        $targetSession = $this->plugin->getSessionManager()->getSession($entity);
        if ($targetSession === null) return;

        $itemConfig = $this->plugin->getConfig()->get("staff_mode.items", []);
        
        // Congelador
        if ($item->getCustomName() === $this->plugin->getMessage("staff_mode.items.freeze.name")) {
            if($entity->hasPermission("staffcore.bypass.freeze")) return;

            $isNowFrozen = !$targetSession->isFrozen();
            $targetSession->setFrozen($isNowFrozen);
            if($isNowFrozen){
                $targetSession->setPreFreezeNameTag($entity->getNameTag());
                $newFreezeNameTag = "§b§lCONGELADO§r\n" . $entity->getNameTag();
                $entity->setNameTag($newFreezeNameTag);

                $damager->sendMessage($this->plugin->getMessage("freeze_target", ["target" => $entity->getName()]));
                $entity->sendTitle($this->plugin->getMessage("freeze_target_message_title"), $this->plugin->getMessage("freeze_target_message_subtitle"));
                $entity->sendMessage($this->plugin->getMessage("freeze_chat_header"));
            } else {
                $entity->setNameTag($targetSession->getPreFreezeNameTag() ?? $entity->getName());
                $targetSession->setPreFreezeNameTag(null);

                $damager->sendMessage($this->plugin->getMessage("unfreeze_target", ["target" => $entity->getName()]));
                $entity->sendMessage($this->plugin->getMessage("unfreeze_target_message"));
            }
        }
        
        // Inspector de Inventario
        if ($item->getCustomName() === $this->plugin->getMessage("staff_mode.items.inventory_viewer.name") && $damager->hasPermission("staffcore.feature.invsee")) {
            $damager->sendForm(new InvSeeChooserForm($entity));
        }

        // Información del Jugador
        if ($item->getCustomName() === $this->plugin->getMessage("staff_mode.items.player_info.name") && $damager->hasPermission("staffcore.feature.playerinfo")) {
            $damager->sendForm(new PlayerInfoForm($entity));
        }

        // Herramienta de Sanción
        if ($item->getCustomName() === $this->plugin->getMessage("staff_mode.items.ban_tool.name") && $damager->hasPermission("staffcore.feature.banui")) {
            $damager->sendForm(new BanForm($entity));
        }
    }
    
    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->getSession($player);
        if ($session === null || !$session->isInStaffMode()) return;

        $event->cancel();
        $item = $event->getItem();
        $itemConfig = $this->plugin->getConfig()->get("staff_mode.items", []);

        // Teletransportador
        if ($item->getCustomName() === $this->plugin->getMessage("staff_mode.items.teleporter.name")) {
            if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_AIR) { // TP Aleatorio
                $onlinePlayers = array_filter($player->getServer()->getOnlinePlayers(), fn(Player $p) => $p->getName() !== $player->getName() && !$p->isCreative());
                if(empty($onlinePlayers)){
                    $player->sendMessage($this->plugin->getMessage("teleporter_no_players"));
                    return;
                }
                $target = $onlinePlayers[array_rand($onlinePlayers)];
                $player->teleport($target->getPosition());
                $player->sendMessage($this->plugin->getMessage("teleporter_random_success", ["target" => $target->getName()]));
            } else { // Atravesar (Click Izquierdo o en bloque)
                $player->teleport($player->getPosition()->addVector($player->getDirectionVector()->multiply(5)));
            }
        }

        // Vanish
        if ($item->getCustomName() === $this->plugin->getMessage("staff_mode.items.vanish.name")) {
            $isNowVanished = !$session->isVanished();
            $session->setVanished($isNowVanished); // Esto ahora actualiza la visibilidad automáticamente
            
            $inv = $player->getInventory();
            $vanishSlot = $itemConfig['vanish']['slot'] ?? 8;
            
            if($isNowVanished){
                $player->sendMessage($this->plugin->getMessage("vanish_on"));
                $inv->setItem($vanishSlot, $inv->getItem($vanishSlot)->setCustomName($this->plugin->getMessage("vanish_item_on_name")));
            } else {
                $player->sendMessage($this->plugin->getMessage("vanish_off"));
                $inv->setItem($vanishSlot, $inv->getItem($vanishSlot)->setCustomName($this->plugin->getMessage("vanish_item_off_name")));
            }
        }
    }

    public function onChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->getSession($player);
        
        // Lógica del Chat de Congelación
        if ($session !== null && $session->isFrozen()) {
            $event->cancel();
            $message = $this->plugin->getMessage("freeze_chat.format", [
                "player" => $player->getName(),
                "message" => $event->getMessage()
            ]);

            // Enviar mensaje al jugador congelado
            $player->sendMessage($message);

            // Enviar mensaje a todo el staff con permiso
            foreach($this->plugin->getServer()->getOnlinePlayers() as $staff) {
                if ($staff->hasPermission("staffcore.chat.freeze")) {
                    $staff->sendMessage($message);
                }
            }
        }
    }
    
    public function onCommandPreprocess(PlayerCommandPreprocessEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->getSession($player);
        if ($session === null) return;

        // Bloquear comandos si está congelado
        if ($session->isFrozen()) {
            $command = explode(" ", strtolower($event->getMessage()))[0];
            $blockedCmds = $this->plugin->getConfig()->get("blocked_commands_on_freeze", []);
            if(in_array(ltrim($command, "/"), $blockedCmds)){
                $event->cancel();
                $player->sendMessage("§cNo puedes usar este comando mientras estás congelado.");
            }
        }
    }
    
    public function onDropItem(PlayerDropItemEvent $event): void {
        $session = $this->plugin->getSessionManager()->getSession($event->getPlayer());
        if ($session !== null && $session->isInStaffMode()) {
            $event->cancel();
        }
    }

    public function onPickupItem(PlayerItemPickupEvent $event): void {
        $session = $this->plugin->getSessionManager()->getSession($event->getOrigin());
        if ($session !== null && $session->isInStaffMode()) {
            $event->cancel();
        }
    }

    public function onExhaust(PlayerExhaustEvent $event): void {
        $player = $event->getPlayer();
        $session = $this->plugin->getSessionManager()->getSession($player);
        if($session !== null && ($session->isInStaffMode() || $session->isFrozen())){
            $event->cancel();
        }
    }
}
