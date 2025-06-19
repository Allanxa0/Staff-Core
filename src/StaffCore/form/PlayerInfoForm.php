<?php

declare(strict_types=1);

namespace StaffCore\form;

use jojoe77777\FormAPI\CustomForm;
use pocketmine\player\Player;
use StaffCore\Main;

class PlayerInfoForm extends CustomForm {

    public function __construct(private Player $target) {
        parent::__construct(null); // Formulario de solo lectura, no necesita handler.

        $plugin = Main::getInstance();

        // Obtener datos de geolocalización de forma asíncrona
        $plugin->fetchGeoData($this->target, function(array $geoData) {
            $this->setTitle("§8Info de " . $this->target->getName());
            
            $health = $this->target->getHealth();
            $maxHealth = $this->target->getMaxHealth();
            $food = $this->target->getHungerManager()->getFood();
            $maxFood = $this->target->getHungerManager()->getMaxFood();
            
            // Mapear ID de dispositivo a nombre legible
            $deviceOS = match($this->target->getNetworkSession()->getPlayerInfo()->getDeviceOS()){
                1 => "Android", 2 => "iOS", 7 => "Windows", 8 => "MacOS", default => "Desconocido"
            };

            // Mapear ID de control a nombre legible
            $controlType = match($this->target->getNetworkSession()->getPlayerInfo()->getCurrentInputMode()){
                1 => "Teclado y Ratón", 2 => "Táctil", 3 => "Mando", 4 => "VR", default => "Desconocido"
            };

            $this->addLabel("§f- §eNombre: §f" . $this->target->getName());
            $this->addLabel("§f- §eGamertag: §f" . $this->target->getDisplayName());
            $this->addLabel("§f- §eSalud: §f" . $health . "/" . $maxHealth);
            $this->addLabel("§f- §eComida: §f" . $food . "/" . $maxFood);
            $this->addLabel("§f- §ePing: §f" . $this->target->getNetworkSession()->getPing() . "ms");
            $this->addLabel("§f- §eDispositivo: §f" . $deviceOS);
            $this->addLabel("§f- §eMétodo de Control: §f" . $controlType);
            $this->addLabel("§f- §eIP: §f" . $this->target->getNetworkSession()->getIp());
            $this->addLabel("§f- §ePaís: §f" . ($geoData['country'] ?? 'No disponible'));
            
            // Enviar el formulario al jugador que lo solicitó (el constructor no tiene acceso al jugador)
            // Esto es un truco para poder enviar el form desde el callback asíncrono.
            $requesterName = $this->getOwningPlayerName();
            if($requesterName !== null){
                $requester = Main::getInstance()->getServer()->getPlayerExact($requesterName);
                if($requester instanceof Player && $requester->isOnline()){
                     $requester->sendForm($this);
                }
            }
        });
    }

    // Un truco para obtener el jugador que solicita el formulario, ya que el callback no lo tiene.
    private ?string $owningPlayerName = null;

    public function getOwningPlayerName() : ?string{
        return $this->owningPlayerName;
    }

    public function sendToPlayer(Player $player) : void{
        $this->owningPlayerName = $player->getName();
        // El formulario real se envía en el callback de fetchGeoData
    }
}
