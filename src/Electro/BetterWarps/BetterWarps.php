<?php

namespace Electro\BetterWarps;

use dktapps\pmforms\CustomForm;
use dktapps\pmforms\element\Input;
use dktapps\pmforms\element\Toggle;
use dktapps\pmforms\element\Dropdown;
use dktapps\pmforms\CustomFormResponse;

use pocketmine\permission\DefaultPermissions;
use pocketmine\permission\PermissionManager;
use pocketmine\permission\Permission;
use pocketmine\utils\Config;

use pocketmine\player\Player;

use pocketmine\plugin\PluginBase;

use pocketmine\event\Listener;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\command\PluginCommand;
use pocketmine\world\Position;

class BetterWarps extends PluginBase implements Listener{

    public function onEnable() : void{
        $warps = new Config($this->getDataFolder() . "Warps.yml", Config::YAML);
        foreach ($warps->getAll() as $warp)
        {
            $this->registerWarp(strtolower($warp["Name"]), $warp["Permission"], $warp["Description"], "/" . $warp["Name"], $warp["OpRequiresPerms"]);
        }
    }

    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
        $warps = new Config($this->getDataFolder() . "Warps.yml", Config::YAML);
        if (!$sender instanceof Player)
        {
            $sender->sendMessage("§cYou must be in-game to run this command");
            return true;
        }
        switch($cmd->getName()) {
            case "warp":
                $warps = new Config($this->getDataFolder() . "Warps.yml", Config::YAML);
                if(!$sender instanceof Player) {
                    $sender->sendMessage("§l§cERROR: §r§aYou must be in-game to execute this command");
                    return true;
                }
                if (!isset($args[0])){
                    $sender->sendMessage("§l§cUsage: §r§a/warp <create/remove/list>");
                    return true;
                }
                switch ($args[0]){
                    case "add":
                    case "create":
                        if (!$sender->hasPermission("betterwarps.cmd") && !$sender->hasPermission("DefaultPermissions::ROOT_OPERATOR")){
                            $sender->sendMessage("§cYou don't have permissions to use this command");
                            return true;
                        }
                        $sender->sendForm($this->warpCreationForm());
                        break;
                    case "list":
                        if (empty($warps->getAll()))
                        {
                            $sender->sendMessage("§cThere are no warps");
                            return true;
                        }
                        $sender->sendMessage("§c§lWarps:");
                        foreach ($warps->getAll() as $warp)
                        {
                            $sender->sendMessage("§a " . $warp["Name"]);
                        }
                        break;
                    case "del":
                    case "delete":
                    case "remove":
                        if (!$sender->hasPermission("betterwarps.cmd") && !$sender->hasPermission("DefaultPermissions::ROOT_OPERATOR")){
                            $sender->sendMessage("§cYou don't have permissions to use this command");
                            return true;
                        }
                        $sender->sendForm($this->warpRemoveForm());
                        break;
                    default:
                        $sender->sendMessage("§l§cUsage: §r§a/warp <create/remove/list>");
                        return true;
                }
                break;
        }
        foreach ($warps->getAll() as $warp)
        {
            switch($cmd->getName())
            {
                case strtolower($warp["Name"]):
                    if (!$this->getServer()->getWorldManager()->isWorldGenerated($warp["Level"]))
                    {
                        $sender->sendMessage("§c§lERROR: §r§aThe world this warp is in does not exist");
                        return true;
                    }
                    if (!$this->getServer()->getWorldManager()->isWorldLoaded($warp["Level"]))
                    {
                        $this->getServer()->getWorldManager()->loadWorld($warp["Level"]);
                    }
                    $sender->teleport(new Position($warp["X"], $warp["Y"], $warp["Z"], $this->getServer()->getWorldManager()->getWorldByName($warp["Level"])));
                    $sender->sendMessage("§aYou have warped to " . $warp["Name"] . "!");
            }
        }
        return true;
    }

    private function warpCreationForm() : CustomForm{
        return new CustomForm(
            "§lCreate a Warp",
            [
                new Input("name", '§rEnter Warp Name', "Mine"),
                new Input("description", '§rEnter Warp Description', "Teleport To The Mine"),
                new Input("permission", '§rEnter Warp Permission (Leave Empty If Permission Is Not Required)', "MineWarp"),
                new Toggle("opRequirePerm", '§rShould OP Players Require Perms to Warp? (Leave Disabled If Warp Does Not Have A Permission)'),
            ],
            function(Player $submitter, CustomFormResponse $response) : void{
                $warps = new Config($this->getDataFolder() . "Warps.yml", Config::YAML);
                $warpName = $response->getString("name");
                $warpDescription = $response->getString("description");
                $warpPermission = $response->getString("permission");
                $warpOpRequirePerm = $response->getBool("opRequirePerm");
                if ($warpName == null)
                {
                    $submitter->sendMessage("§l§cERROR: §r§aYou have entered an invalid warp name");
                    return;
                }
                if ($warps->get($warpName)){
                    $submitter->sendMessage("§l§cERROR: §r§aA warp with that name already exists");
                    return;
                }
                if ($warpDescription == null)
                {
                    $submitter->sendMessage("§l§cERROR: §r§aYou have entered an invalid warp description");
                    return;
                }
                if ($warpPermission == null)
                {
                    $warpPermission = "betterwarps.warp";
                }

                $warps->setNested($warpName . ".Name", $warpName);
                $warps->setNested($warpName . ".Level", $submitter->getWorld()->getFolderName());
                $warps->setNested($warpName . ".X", $submitter->getPosition()->getX());
                $warps->setNested($warpName . ".Y", $submitter->getPosition()->getY());
                $warps->setNested($warpName . ".Z", $submitter->getPosition()->getZ());
                $warps->setNested($warpName . ".Description", $warpDescription);
                $warps->setNested($warpName . ".Permission", $warpPermission);
                $warps->setNested($warpName . ".OpRequiresPerms", $warpOpRequirePerm);
                $warps->save();
                $this->registerWarp(strtolower($warpName), $warpPermission, $warpDescription, "/" . $warpName, $warpOpRequirePerm);
                $submitter->sendMessage("§a" . $warpName . " warp has been successfully created!\nYou may need to rejoin the server for the command to show in chat.");
            },
        );
    }

    private function warpRemoveForm() : CustomForm{
        $warps = new Config($this->getDataFolder() . "Warps.yml", Config::YAML);
        $list = [];
        foreach ($warps->getAll() as $warp)
        {
            $list[] = $warp["Name"];
        }
        return new CustomForm(
            "§lRemove a Warp",
            [
                new Dropdown("warps", "Select A Warp To Remove", $list),
            ],
            function(Player $submitter, CustomFormResponse $response) use ($list) : void{
                $warps = new Config($this->getDataFolder() . "Warps.yml", Config::YAML);
                $warpName = $response->getInt("warps");

                if ($warpName == null){
                    $submitter->sendMessage("§l§cERROR: §r§aYou selected an invalid warp");
                    return;
                }
                $warpName = $list[$response->getInt("warps")];
                $this->unregisterWarp($warps->getNested($warpName . ".Name"), $warps->getNested($warpName . ".Permission"));
                $warps->remove($warpName);
                $warps->save();
                $submitter->sendMessage("§aThe warp " . $warpName . " has been successfully removed");
            },
        );
    }

    public function registerWarp($name, $permission, $description = "", $usage = "", $opRequiresPerms = false)
    {
        if ($permission != "betterwarps.warp")
        {
            PermissionManager::getInstance()->addPermission(new Permission($permission));

            if ($opRequiresPerms == false)
            {
                $opRoot = PermissionManager::getInstance()->getPermission(DefaultPermissions::ROOT_OPERATOR);
                $opRoot->addChild($permission, true);
            }
        }

        $command = new PluginCommand($name, $this, $this);
        $command->setDescription($description);
        $command->setPermission($permission);
        $command->setUsage($usage);
        $this->getServer()->getCommandMap()->register($name, $command);
    }

    public function unregisterWarp($name, $permission)
    {
        if ($permission != "betterwarps.warp")
        {
            PermissionManager::getInstance()->removePermission(PermissionManager::getInstance()->getPermission($permission));

            $opRoot = PermissionManager::getInstance()->getPermission(DefaultPermissions::ROOT_OPERATOR);
            if (in_array($permission, $opRoot->getChildren()))
            {
                $opRoot->removeChild($permission);
            }
        }
        $this->getServer()->getCommandMap()->unregister($this->getCommand(strtolower($name)));
    }

}
