<?php

declare(strict_types=1);

namespace kaidoMC\RegionProtect;

use kaidoMC\RegionProtect\EventListener;
use kaidoMC\RegionProtect\Provider\VectorAdjust;
use kaidoMC\RegionProtect\Utils\Configuration;
use kaidoMC\RegionProtect\Utils\SelectVector;

use jojoe77777\FormAPI\CustomForm;

use pocketmine\player\Player;

use pocketmine\command\CommandSender;
use pocketmine\command\Command;

use pocketmine\plugin\PluginBase;

use pocketmine\utils\SingleTonTrait;
use pocketmine\utils\TextFormat;

class RegionProtect extends PluginBase {
	use SingleTonTrait;

	/**
	 * @var VectorAdjust $vectorAdjust;
	 */
	private VectorAdjust $vectorAdjust;

	protected function onLoad(): void {
		self::setInstance($this);
		if (!is_dir($this->getDataFolder() . "regions/")) {
			@mkdir($this->getDataFolder() . "regions/");
		}
		$this->saveDefaultConfig();
	}

	protected function onEnable(): void {
		Configuration::initConfig($this);
		$this->vectorAdjust = new VectorAdjust($this);
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
	}

	/**
	 * @param CommandSender $sender
	 * @param Command $command
	 * @param string $label
	 * @param array $args
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		if (!($sender instanceof Player)) {
			$sender->sendMessage("Use this command in game!");
			return false;
		}
		if (isset($args[0])) {
			switch ($args[0]) {
				case "wand":
					$sender->getInventory()->addItem(SelectVector::getItem($sender));
					break;
				case "create":
					if ((SelectVector::getFirstVector($sender) != null) and (SelectVector::getSecondVector($sender) != null)) {
						$this->getBasicForm($sender);
					} else {
						$sender->sendMessage("Unable to create if one of the two slots is not filled in completely.");
					}
					break;
				case "delete":
					if (!isset($args[1])) {
						$sender->sendMessage(TextFormat::RED . "You must enter a region title to perform the removal process!");
						return false;
					}
					array_shift($args);
					$this->getVectorAdjust()->removeLocation($sender, join(" ", $args));
					break;
				case "edit":
					if (!isset($args[1])) {
						$sender->sendMessage(TextFormat::RED . "You need to enter the title of the region to be able to edit it.
");
						return false;
					}
					array_shift($args);
					$this->getVectorAdjust()->adjustLocation($sender, join(" ", $args));
					break;
				case "list":
					$sender->sendMessage(TextFormat::YELLOW . "Total Regions in the world: (" . count($this->getVectorAdjust()->getLocations()) . ")");
					foreach ($this->getVectorAdjust()->getLocations() as $file) {
						$config = $this->getVectorAdjust()->getLocation(explode(".", $file)[0]);
						if ($config != null) {
							if ($config->get("World") != $sender->getWorld()->getDisplayName()) {
								continue;
							}
							$sender->sendMessage(TextFormat::GREEN . explode(".", $file)[0] . ": " . TextFormat::DARK_GRAY . $config->get("FirstVector")["X"] . " " . $config->get("FirstVector")["Y"] . " " . $config->get("FirstVector")["Z"]);
						}
					}
					break;
				default:
					$sender->sendMessage("Usage: /region <wand|create|edit|delete|list>");
			}
		} else {
			$sender->sendMessage("Usage: /region <wand|create|edit|delete|list>");
		}
		return true;
	}

	/**
	 * @param Player $sender
	 */
	private function getBasicForm(Player $sender): void {
		$firstVector = SelectVector::getFirstVector($sender);
		$secondVector = SelectVector::getSecondVector($sender);
		$X1 = $firstVector->getX();
		$X2 = $secondVector->getX();
		$Y1 = $firstVector->getY();
		$Y2 = $secondVector->getY();
		$Z1 = $firstVector->getZ();
		$Z2 = $secondVector->getZ();

		$form = new CustomForm(function (Player $sender, ?array $result) use ($X1, $X2, $Y1, $Y2, $Z1, $Z2): void {
			if ($result === null) {
				return;
			}
			if (!preg_match('/^[\w]+$/', $result[2])) {
				$sender->sendMessage(TextFormat::RED . "Invalid region name! Only alphanumeric characters allowed.");
				return;
			}
			if ($result[2] != null and $result[3] != null) {
				$this->getVectorAdjust()->setLocation($sender, $result[2], $result[3], [$X1, $Y1, $Z1], [$X2, $Y2, $Z2]);
			} else {
				$sender->sendMessage(TextFormat::RED . "Invalid information, please try again!");
			}
		});
		$form->setTitle("Adjust Location");
		$form->addLabel("Selected information:");
		$form->addLabel("World: " . $sender->getWorld()->getDisplayName() . "\nFirst Position: " . $X1 . " " . $Y1 . " " . $Z1 . "\nSecond Position:  " . $X2 . " " . $Y2 . " " . $Z2);
		$form->addInput("Enter the title of the region.", "Mushroom", "Cow");
		$form->addInput("Enter the name of the region (The content will be displayed when the player enters the region). ", "Mushroom", "Kingdom");
		$sender->sendForm($form);
	}

	/**
	 * @return VectorAdjust
	 */
	public function getVectorAdjust(): VectorAdjust {
		return $this->vectorAdjust;
	}
}
