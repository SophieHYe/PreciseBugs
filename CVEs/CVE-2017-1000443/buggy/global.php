<?php

/* 
 * GLOBAL CONFIGURATION FILE
WARNING EDITING THIS DOCUMENT WITHOUT UNDERSTANDING WHAT EACH OPTION DOES CAN HAVE DIRE CONSEQUENCES

Each option allows for the manipulation of global values. These affect the game engine and should be tested prior to pushing to production.
*/

$GAMEVERSION = "0.1.47"; // Game version
$MAXEPCOUNT = 64000; // Max Experience allowed
$MEP4LVL = 640; // Max experience per level

// Site Registration Configurations

$REGISTRATION = '1';  // Enables users to register accounts
$AUTHTOJOIN = '1'; // Forces users to confirm their emails prior to logging in for the first time
$adminrestrict = false; // Set the site into offline mode and prevent users from navigating any further portion of the site
$adminip = ""; // Set the Administrator's IP Address

// Jail Chance Config

$jailDefaultChance = 40; // Base Probability that the user's task will fail and go to jail
$jailMaxBail = 25000; // Max amount posted for user bail
$jailMinBail = 1000; // Minimum amount posted for user bail
$jailBustOutChance = 25; // Base Probability that another hacker will successfully break user out of jail

// FBI Cron Config

$fbiRunTime = 120; // How long until the FBI attempt to bust hackers on the FBI list (Default 2 Hours)
$fbiSuccessChance = 80; // What is the probability of the hacker being caught by the FBI (80%)

// FBI Hack Chance Config

$fbiServerPass = 40; // Probability that the user will crack the FBI server password
$fbiServerModify = 65; // Probability that the user will successfully modify the FBI Database
$fbiServerFail = 78; // Probability that the user will be caught modifying the FBI Database

// Hack Chance Config

$pvpHackervsHacker = 32; // Default value in which a launched attack will successfully infect
$pvpHackervsBot = 85; // Default value vs a bot
$pvpHackervsServer = 45; // Default value vs a server

// Small Hack Chance Config

$baseSmallJobSuccess = 34; 

// Hacker Ethic Boosts

$hatBlackAttack = .25;
$hatBlackDefense = .5;
$hatBlackSkill = .10;

$hatWhiteAttack = .5;
$hatWhiteDefense = .25;
$hatWhiteDefense = .10;

$hatGrayAttack = .15;
$hatGrayDefense = .15;
$hatGraySkill = .15; // ( DO NOT DISCLOSE, GRAY HATS CAN LEARN SKILLS FASTER THAN NORMAL DUE TO THEIR LACK OF SPECIALIZATION  )

$hatSwitch = 1; // How many times can a player change their hacker ethic within 24 hours.

// Server Config

$serverDecayValue = .02; // Per hour how many points to remove from server health
$serverFirewallDecayValue = .05; // Per hour how many points to remove from server firewall
$serverRaidDecay = .0023; // Per hour how many points to remove from server storage array health

$serverFireSharingRev = 200; // Per hour how much money is made from FSS
$serverPornRev = 75; // Per hour how much money is made from Porn
$serverSpamRev = 30; // Per hour how much money is made from Spam
$serverFtpRev = 0; // Per hour how much money is made from FTP

// PC Config

$pcDecayValue = .012; // Per hour how many points to remove from PC health
$pcFirewallDecayValue = .023; // Per hour how many points to remove from PC firewall
$pcHddDecayValue = .01; // Per hour how many points to remove from PC HDD health

// ISP Config

$ispRefreshIP = 5; // How many times a player can request to have their IP changed within 24 hours 
$ispCostperRefresh = 100; // Cost in $ for refreshing an IP address
$ispServiceFeedialup = 0; // Cost in $ for Dialup Service
$ispServiceFeedT1 = 1000; // Cost in $ for T1 Service (1.5 Mbps)
$ispServiceFeedT2 = 2000; // Cost in $ for T2 Service (Bonded T1, 3.0 Mbps)
$ispServiceFeedT3 = 3000; // Cost in $ for T3 Service (40 Mbps)
$ispServiceFeedOC3 = 8000; // Cost in $ for OC1 Service (155.52 Mbps)
$ispServerCost = 20000; // Cost in $ for purchase of 1 Server
$ispServerMaintCost = 5000; // Cost in $ for daily server maintenance

// Hacker for Hire Config

$h4hTraceUser = 10000; // Cost in $ to trace a specifc user's IP address
$h4hTraceHacked = 8000; // Cost in $ to find out who hacked you
$h4hTraceUserFail = $h4hTraceUser / 2; // Return half the money for failing to trace the user

//SERVER DEFINED (VARIABLES THAT THE SYSTEM SETS FOR ITSELF)

$serverCoolDown = false; // Game server exausted resources to perform actions
$systemDDoS = false; // Game system has been brought down by game-wide DDoS
$systemInfected = false; // Game server is serving outside privlidge.
$reset_imminent = false; // Game server is under (fictional) attack and is soon to fail
$systemwatchdog = false; // Anti-cheat engine
$midnight_reset = false; // Enables the midnight reset (SEE MIDNIGHT RESET)

// SYSTEM WATCHDOG CONFIGURATION

$sysguard = true;
$reportonly = true; // Only generate a report and don't perform freeze/jail tasks.
$guardmode = "relaxed"; // (Relaxed/Enforcing)
$jailmode = false; // Send offenders to jail (ONLY 1 CAN BE ACTIVE)
$prisonmode = false; // Send offenders to prison (ONLY 1 CAN BE ACTIVE)
$sysuserid = 9; // Account system id to perform watchdog actions as
$createticket = false; // Autogen system ticket for potential disrupters
$freezeuser = false; // Lock the user to the suspended screen until an administrator deems appropriate

// BUSINESS COMMERCE CONFIGURATION

$businesses = true; // Enable or disable the creation of businesses
$reset_resistance = true; // prevent businesses from being forgotten on system reset

// CURRENCY / EXCHANGE CONFIGURATION

$defaultcurrency = "USD";
$cryptocurrency = "BTC";
$cryptoUseLiveFeed = false; // Allow live market value to affect game market
$exchange = false; // Toggle currency exchange system
$trade = false; // Toggle stock trade system
$stockUseLiveFeed = false; // Allow live stock market value to affect game market

// ADMIN CONFIGURATION

$idOverride = 1; // DB UserID with Highest Priv
$adminPlay = false; // Toogle admin's ability to play game
$modPlay = true; // Toggle mod's ability to play game


// MORE OPTIONS COMING LATER
