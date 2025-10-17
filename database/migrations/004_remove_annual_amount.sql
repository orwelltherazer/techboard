-- Suppression du champ annual_amount de la table contracts
-- Ce champ n'est ni utile ni pertinent

ALTER TABLE contracts DROP COLUMN annual_amount;
