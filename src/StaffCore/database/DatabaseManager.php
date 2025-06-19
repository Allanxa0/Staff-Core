<?php

declare(strict_types=1);

namespace StaffCore\database;

use pocketmine\inventory\ArmorInventory;
use pocketmine\inventory\PlayerInventory;
use pocketmine\player\Player;
use SQLite3;

class DatabaseManager {

    private SQLite3 $db;

    public function __construct(string $path) {
        $this->db = new SQLite3($path);
        $this->initializeTables();
    }

    private function initializeTables(): void {
        $this->db->exec("CREATE TABLE IF NOT EXISTS bans (
            username TEXT PRIMARY KEY,
            ip_address TEXT,
            staff_member TEXT NOT NULL,
            reason TEXT NOT NULL,
            ban_type TEXT NOT NULL,
            ban_date INTEGER NOT NULL,
            expire_date INTEGER NOT NULL
        );");

        // Tabla unificada para todos los datos persistentes del jugador
        $this->db->exec("CREATE TABLE IF NOT EXISTS player_data (
            username TEXT PRIMARY KEY,
            is_in_staff_mode BOOLEAN NOT NULL DEFAULT 0,
            is_frozen BOOLEAN NOT NULL DEFAULT 0,
            is_vanished BOOLEAN NOT NULL DEFAULT 0,
            pre_freeze_nametag TEXT,
            inventory_contents BLOB,
            armor_contents BLOB
        );");
    }
    
    // --- Gestión de Datos del Jugador ---

    public function getPlayerData(string $username): ?array {
        $username = strtolower($username);
        $stmt = $this->db->prepare("SELECT * FROM player_data WHERE username = :username");
        $stmt->bindValue(":username", $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $data = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($data) {
            $data['inventory_contents'] = $data['inventory_contents'] ? unserialize(base64_decode($data['inventory_contents'])) : [];
            $data['armor_contents'] = $data['armor_contents'] ? unserialize(base64_decode($data['armor_contents'])) : [];
        }
        
        return $data ?: null;
    }

    public function setPlayerState(string $username, string $column, $value): void {
        $username = strtolower($username);
        // Asegurarse de que el registro exista
        $this->db->exec("INSERT OR IGNORE INTO player_data (username) VALUES ('$username')");
        
        $stmt = $this->db->prepare("UPDATE player_data SET $column = :value WHERE username = :username");
        $stmt->bindValue(":value", is_bool($value) ? (int)$value : $value);
        $stmt->bindValue(":username", $username);
        $stmt->execute();
    }

    public function saveInventory(Player $player): void {
        $username = strtolower($player->getName());
        $inventory = base64_encode(serialize($player->getInventory()->getContents()));
        $armor = base64_encode(serialize($player->getArmorInventory()->getContents()));

        $this->db->exec("INSERT OR IGNORE INTO player_data (username) VALUES ('$username')");

        $stmt = $this->db->prepare("UPDATE player_data SET inventory_contents = :inventory, armor_contents = :armor WHERE username = :username");
        $stmt->bindValue(":username", $username);
        $stmt->bindValue(":inventory", $inventory, SQLITE3_BLOB);
        $stmt->bindValue(":armor", $armor, SQLITE3_BLOB);
        $stmt->execute();
    }
    
    public function restoreInventory(Player $player): void {
        $username = strtolower($player->getName());
        $data = $this->getPlayerData($username);

        if ($data && !empty($data['inventory_contents'])) {
            $player->getInventory()->setContents($data['inventory_contents']);
            $player->getArmorInventory()->setContents($data['armor_contents']);
            
            // Limpiar el inventario guardado para evitar restauraciones duplicadas
            $stmt = $this->db->prepare("UPDATE player_data SET inventory_contents = NULL, armor_contents = NULL WHERE username = :username");
            $stmt->bindValue(":username", $username);
            $stmt->execute();
        }
    }

    // --- Gestión de Sanciones ---
    
    public function banPlayer(string $username, ?string $ip, string $staffMember, string $reason, string $banType, int $expireDate): void {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO bans (username, ip_address, staff_member, reason, ban_type, ban_date, expire_date) VALUES (:username, :ip, :staff, :reason, :type, :ban_date, :expire_date)");
        $stmt->bindValue(":username", strtolower($username));
        $stmt->bindValue(":ip", $ip);
        $stmt->bindValue(":staff", $staffMember);
        $stmt->bindValue(":reason", $reason);
        $stmt->bindValue(":type", $banType);
        $stmt->bindValue(":ban_date", time());
        $stmt->bindValue(":expire_date", $expireDate);
        $stmt->execute();
    }

    public function getBanInfo(Player $player): ?array {
        $username = strtolower($player->getName());
        $ip = $player->getNetworkSession()->getIp();
        
        $stmt = $this->db->prepare("SELECT * FROM bans WHERE username = :username OR (ip_address = :ip AND ban_type = 'IP') ORDER BY expire_date DESC LIMIT 1");
        $stmt->bindValue(":username", $username);
        $stmt->bindValue(":ip", $ip);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if($result === false) return null;

        if($result['expire_date'] !== PHP_INT_MAX && time() > $result['expire_date']){
            $this->unbanPlayer($username);
            return null;
        }
        return $result;
    }

    public function getAllBans(): array {
        $bans = [];
        $result = $this->db->query("SELECT * FROM bans");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            // Verificar si el baneo ha expirado aquí también
            if($row['expire_date'] !== PHP_INT_MAX && time() > $row['expire_date']) {
                $this->unbanPlayer($row['username']);
            } else {
                $bans[] = $row;
            }
        }
        return $bans;
    }

    public function isBanned(string $username): bool {
        $username = strtolower($username);
        $stmt = $this->db->prepare("SELECT expire_date FROM bans WHERE username = :username");
        $stmt->bindValue(":username", $username);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result === false) return false;

        if ($result['expire_date'] !== PHP_INT_MAX && time() > $result['expire_date']) {
            $this->unbanPlayer($username);
            return false;
        }
        
        return true;
    }

    public function unbanPlayer(string $username): bool {
        $stmt = $this->db->prepare("DELETE FROM bans WHERE username = :username");
        $stmt->bindValue(":username", strtolower($username));
        $stmt->execute();
        return $this->db->changes() > 0;
    }
}
