<?php

declare(strict_types=1);

namespace StaffCore\form;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\player\Player;
use pocketmine\Server;
use StaffCore\Main;

class BanForm extends CustomForm {

    public function __construct(private Player $target) {
        parent::__construct(function (Player $player, ?array $data) {
            if ($data === null) return;

            $plugin = Main::getInstance();
            $isPermanent = (bool)$data['permanent'];
            $reason = empty($data['reason']) ? "Sin razón especificada" : $data['reason'];
            $isIpBan = (bool)$data['ip_ban'];
            
            $days = (int)$data['days'];
            $hours = (int)$data['hours'];
            $minutes = (int)$data['minutes'];
            
            $duration = ($days * 86400) + ($hours * 3600) + ($minutes * 60);

            if (!$isPermanent && $duration <= 0) {
                $player->sendMessage($plugin->getMessage("prefix") . "§cLa duración del baneo debe ser mayor a 0.");
                return;
            }

            $expireDate = $isPermanent ? PHP_INT_MAX : time() + $duration;
            $banType = $isIpBan ? $plugin->getMessage("ban_type_ip") : $plugin->getMessage("ban_type_normal");
            $targetIp = $this->target->getNetworkSession()->getIp();

            $plugin->getDatabaseManager()->banPlayer(
                $this->target->getName(), 
                $isIpBan ? $targetIp : null,
                $player->getName(), 
                $reason,
                $banType,
                $expireDate
            );

            // Formatear tiempo para mensajes
            if ($isPermanent) {
                $timeFormat = $plugin->getMessage("duration_permanent");
            } else {
                $timeFormat = ($days > 0 ? "$days días " : "") . ($hours > 0 ? "$hours horas " : "") . ($minutes > 0 ? "$minutes minutos" : "");
            }
            
            // Kickear al jugador
            $kickMessage = $plugin->getMessage("ban_kick_message", [
                "reason" => $reason,
                "time" => trim($timeFormat)
            ]);
            $this->target->kick($kickMessage);

            // Mensaje global
            $broadcastMessage = $plugin->getMessage("ban_broadcast", [
                "target" => $this->target->getName(),
                "staff" => $player->getName(),
                "reason" => $reason
            ]);
            Server::getInstance()->broadcastMessage($broadcastMessage);
            
            // Webhook de Discord
            $plugin->sendDiscordWebhook(
                "Jugador Baneado", "", 0xFF0000,
                [
                    "Jugador" => $this->target->getName(),
                    "Staff" => $player->getName(),
                    "Tipo" => $banType,
                    "Razón" => $reason,
                    "Duración" => trim($timeFormat),
                    "IP" => $targetIp
                ]
            );
        });

        $this->setTitle("§8Sancionar a " . $this->target->getName());
        $this->addLabel("Estás a punto de sancionar a " . $this->target->getName() . ". Por favor, completa los campos.");
        $this->addInput("Razón del Baneo", "ej: Uso de hacks (X-Ray)", "", "reason");
        $this->addToggle("Baneo Permanente", false, "permanent");
        $this->addToggle("¿Baneo por IP?", false, "ip_ban");
        $this->addStepSlider("Días", array_map('strval', range(0, 30)), 0, "days");
        $this->addStepSlider("Horas", array_map('strval', range(0, 23)), 0, "hours");
        $this->addStepSlider("Minutos", array_map('strval', range(0, 59)), 5, "minutes");
    }
}
