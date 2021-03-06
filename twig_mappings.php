<?php
/* This file maps PHP files to Twig files. When a PHP file is accessed, it takes the filename
 * being accessed ($screen) and checks this array for a match. If one is found, it imports the 
 * associated Twig template as specified here. Useful for page-specific <head> content.
 * 
 * The Twig template should be defined in extensions.twig.
 * 
 * Mapping format: "Screen_Name" => "Template_Name",
 */
$mappings = array(
	"admin-manageTNRS" => "tnrs",
    "therapist-akaunting" => "akaunting",
    "therapist-clientlist" => "therapist-clientlist",
    "home" => "home",
    "developer-clinics" => "clinics",
    "leader-clinic" => "clinics",
    "administrator-clinics" => "clinics",
    "therapist-distributeReports" => "distributeReports",
    "admin-users" => "manage-users",
    "therapist-filing-cabinet" => "filing-cabinet",
    "therapist-reports" => "reports",
    "therapist-viewVideos" => "filing-cabinet",
    "admin-analysis" => "filing-cabinet-analysis"
);
$mappings;