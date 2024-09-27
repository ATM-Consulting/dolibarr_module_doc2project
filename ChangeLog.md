# Change Log
All notable changes to this project will be documented in this file.

## Unreleased

## 3.4
- FIX DA025560 : Line date wasn't following on tasks - *27/09/2024* - 3.4.5
- FIX : Missing feature allowing, during the conversion of a nomenclature into a task, to take only the quantity of the nomenclature without multiplying by the quantity of the document line   - *02/07/2024* - 3.4.4  
- FIX : DA024918 - les lignes libres de service n'étaient plus reprises lors de la création d'un projet et de ses tâches depuis une proposition commerciale - *30/04/2024* - 3.4.3  
- FIX : $this->db remove and msg  added on conf description   - *27/02/2024* - 3.4.2  
- FIX : Fatal this->db was always empty - *22/01/2024* - 3.4.1
- NEW :   Changed Dolibarr compatibility range to 12 min - 19 max  	- *29/11/2023* - 3.4.0
	  Changed PHP compatibility range to 7.0 min - 8.2 max		- *29/11/2023* - 3.4.0


## 3.3

- NEW: Nouvelle conf permettant de cocher par défaut la case pour suivre les tâches et le temps passé lors de la création d'un projet - *24/08/2023* - 3.3.0


## 3.2

- FIX : DA023853 - Inversion du test de version - *27/09/2023* - 3.2.6  
- FIX : Inversion du test de version - *05/07/2023* - 3.2.4  
- FIX : Compat Dolibarr V18 *12/06/2023* - 3.2.4
- FIX : update llx_extrafields sans where *10/03/2023* - 3.2.3
- FIX : Compatibilité - Token  *21/01/2023* - 3.2.2
- FIX : Missed size parameter in addExtrafield module's init *31/08/2022* - 3.2.1
- NEW : Ajout de la class TechATM pour l'affichage de la page "A propos" *11/05/2022* 3.2.0

## 3.1
- FIX: Compatibility V16 - token and family - *07/06/2022* - 3.1.5
- FIX : Missed traduction *03/02/2022* - 3.1.4
- FIX: lors de la génération d'un projet (déjà existant) depuis une propal un warning   - *04/02/2022* - 3.1.3
  s'affichait sur l'affectation de valeur sur un extrafield
- FIX: Création des postes de travail n'étaient pas fonctionnel en fonction des sous produits - *14/12/2021* - 3.1.1
- NEW: Nouvelle conf permettant d'avoir en permanence le nom de la société dans le titre du projet - *17/11/2021* - 3.1.0

## 3.0
- FIX: compatibility with WorkstationATM for Dolibarr 14 - *12/10/2021* - 3.0.0
- **REQUIRES WorkstationATM >= 2.0**

## 2.1
- FIX: Dolibarr V13 Trigger compatibility - *25/03/2021* - 2.1.1

## Version 2.0
- no change log up to this point

