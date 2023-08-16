# Change Log
All notable changes to this project will be documented in this file.

## Version 1.0

### Changed

- FIX : `Interface.php` has fatal errors (invisible to user) due to SQL
  injection of empty input values - *29/06/2022* - 1.1.7
- FIX : Can't create more product prices if multidevise is enable - *01/06/2022* - 1.1.6
- FIX : UX Changes between DOL 13.0 and 14.0 so we pull the qsp form under addline tpl - *02/05/2022* - 1.1.5
- FIX : tvatx must not be converted to int, because it can have decimals and specific tva code - *30/03/2022* - 1.1.4
- FIX : Fill the unit price to be used by the addline action of fourn/commande/card.php which has changed between V12 and V13 - *22/12/2021* - 1.1.3
- FIX : Compatibility V13 - Add token renewal - *18/05/2021* - 1.1.2
- FIX [2020-12-10] Fetch and display the OF select value when link an OF on CF (OF select on Dolibarr form AND OF select on Quicksupplier form)
