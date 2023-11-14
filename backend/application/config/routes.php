<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;


$route['questions/get-audit-questions']['GET'] = 'QuestionController/getAuditQuestions';

$route['audits/get-databases-paginated']['GET'] = 'AuditController/getDatabasesPaginated';
$route['audits/get-records-paginated']['GET'] = 'AuditController/getRecordsPaginated';
$route['audits/get-records']['GET'] = 'AuditController/getRecords';
$route['audits/get-databases']['GET'] = 'AuditController/getDatabases';
$route['audits/get-item-scores']['GET'] = 'AuditController/getItemScores';
$route['audits/get-area-scores']['GET'] = 'AuditController/getAreaScores';
$route['audits/get-finding-scores']['GET'] = 'AuditController/getFindingScores';
$route['audits/get-bsc-scores']['GET'] = 'AuditController/getBSCScores';
$route['audits/get-bsc-item-scores']['GET'] = 'AuditController/getBSCItemScores';
$route['audits/get-dashboard-data']['GET'] = 'AuditController/getDashboardData';
$route['audits/export-databases']['POST'] = 'AuditController/exportDatabases';
$route['audits/export-item-scores']['POST'] = 'AuditController/exportItemScores';
$route['audits/export-area-scores']['POST'] = 'AuditController/exportAreaScores';
$route['audits/export-finding-scores']['POST'] = 'AuditController/exportFindingScores';
$route['audits/export-bsc-scores']['POST'] = 'AuditController/exportBSCScores';
$route['audits/export-bsc-item-scores']['POST'] = 'AuditController/exportBSCItemScores';
$route['audits/check']['POST'] = 'AuditController/check';
$route['audits/(:num)/findings']['GET'] = 'AuditController/getFindings/$1';
$route['audits']['POST'] = 'AuditController/store';
$route['audits/(:num)']['POST'] = 'AuditController/update/$1';
$route['audits/check-is-reviewed/(:num)']['GET'] = 'AuditController/checkIsReviewed/$1';
$route['audits/check-is-followed-up/(:num)']['GET'] = 'AuditController/checkIsFollowedUp/$1';
$route['audits/check-is-completed/(:num)']['GET'] = 'AuditController/checkIsCompleted/$1';

$route['action-plans']['POST'] = 'ActionPlanController/store';
$route['action-plans/update']['POST'] = 'ActionPlanController/update';

$route['mqaa-items']['GET'] = 'MQAAItemController/index';
$route['mqaa-items/(:num)']['GET'] = 'MQAAItemController/show/$1';
$route['mqaa-items']['POST'] = 'MQAAItemController/store';
$route['mqaa-items/(:num)']['POST'] = 'MQAAItemController/update/$1';
$route['mqaa-items/(:num)']['DELETE'] = 'MQAAItemController/delete/$1';

$route['areas']['GET'] = 'AreaController/index';
$route['areas/(:num)']['GET'] = 'AreaController/show/$1';
$route['areas']['POST'] = 'AreaController/store';
$route['areas/(:num)']['POST'] = 'AreaController/update/$1';
$route['areas/(:num)']['DELETE'] = 'AreaController/delete/$1';

$route['users']['GET'] = 'UserController/index';
$route['users/(:num)']['GET'] = 'UserController/show/$1';
$route['users']['POST'] = 'UserController/store';
$route['users/(:num)']['POST'] = 'UserController/update/$1';
$route['users/(:num)']['DELETE'] = 'UserController/delete/$1';
$route['users/login']['POST'] = 'UserController/login';
$route['users/logout']['POST'] = 'UserController/logout';
$route['users/vss-verify']['POST'] = 'UserController/vssVerify';

$route['access/check']['POST'] = 'AccessController/check';

$route['roles']['GET'] = 'RoleController/index';
$route['email/send-notification-email']['POST'] = 'EmailController/SendNotificationEmail';