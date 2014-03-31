--TEST--
Some crazy queries:)
--FILE--
<?php

use Flunorette\SelectQuery;
use Flunorette\SqlLiteral;

include_once dirname(__FILE__) . '/../connect.inc.php';
include_once dirname(__FILE__) . '/../nette.tester.sim.php';

//======================= UZMIPOPUST.RS =======================//
//SELECT `offer`.*
//FROM `offer`
//INNER JOIN `server` ON `offer`.`server_id` = `server`.`id`
//INNER JOIN `city` ON `offer`.`city_id` = `city`.`id`
//WHERE (`deleted` = 0) AND (`offer`.`category_id` IS NOT NULL) AND (`offer`.`city_id` IN (2)) AND
//(`offer`.`category_id` IN (1)) AND (NOW() BETWEEN `offer`.`start_time` AND `offer`.`end_time`) AND
//(TO_DAYS(`offer`.`start_time`) = TO_DAYS(NOW())) AND (`active` = 1) AND (`tags` LIKE '%krom%' OR
//`title` LIKE '%krom%' OR `server`.`name` LIKE '%krom%' OR `city`.`name` LIKE '%krom%')
//GROUP BY `offer`.`id`
//ORDER BY EXISTS (
//SELECT *
//FROM modification
//WHERE
// (modification.offer_id = offer.id) AND (modification.type = "1024") AND (NOW() BETWEEN
//modification.start_time AND modification.end_time)
// ) DESC, DATE(`offer`.`start_time`) DESC, `offer`.`server_id` IN (37,7,70,21,72,18,73,8,10,13,74,51)
//DESC, `offer`.`start_time` DESC
//LIMIT 30
//OFFSET 0



//======================= WRATE.IT =======================//
//SELECT
//    `v_branches_with_rating`.*
//    , AVG(`rating_value`) AS `avg_rating`
//FROM
//    (SELECT
//        `branches`.`id` AS `id`
//        , `branches`.`name` AS `name`
//        , `branches`.`company_id` AS `company_id`
//        , `branches`.`street` AS `street`
//        , `branches`.`city_id` AS `city_id`
//        , `branches`.`gps` AS `gps`
//        , `branches`.`phone` AS `phone`
//        , `branches`.`email` AS `email`
//        , `branches`.`note` AS `note`
//        , `branches`.`brand_id` AS `brand_id`
//        , `branches`.`created_on` AS `created_on`
//        , `branches`.`created_by` AS `created_by`
//        , `branches`.`modified_on` AS `modified_on`
//        , `branches`.`modified_by` AS `modified_by`
//        , `branches`.`deleted_on` AS `deleted_on`
//        , `branches`.`deleted_by` AS `deleted_by`
//        , `branches_categories`.`category_id` AS `category_id`
//        , `criterion_ratings`.`criterion_id` AS `criterion_id`
//        , `criterion_ratings`.`value` AS `rating_value`
//        , `ratings`.`created_on` AS `rated_on`
//        , `ratings`.`created_by` AS `rated_by`
//    FROM
//        `branches`
//        LEFT JOIN `branches_categories`
//            ON (
//                `branches`.`id` = `branches_categories`.`branch_id`
//            )
//        LEFT JOIN `ratings`
//            ON (
//                `branches_categories`.`id` = `ratings`.`branch_category_id` AND (`ratings`.`created_by` IN (26))
//            )
//        LEFT JOIN `criterion_ratings`
//            ON (
//                `criterion_ratings`.`rating_id` = `ratings`.`id`
//            )
//    ) AS v_branches_with_rating
//    INNER JOIN `branch_search_cache`
//        ON `v_branches_with_rating`.`id` = `branch_search_cache`.`id`
//WHERE (
//        `v_branches_with_rating`.`deleted_on` IS NULL
//    )
//    AND (
//        `branch_search_cache`.`txt` LIKE '%blaha%'
//    )
//GROUP BY `id`
//ORDER BY `avg_rating` DESC
//    , `name` ASC
//LIMIT 10 OFFSET 0

?>
--EXPECTF--
