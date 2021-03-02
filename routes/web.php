<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
$route = env('PACKAGE_ROUTE', '');
Route::get('/', function () {
    return "heel";//view('welcome');
});
/*
  Accessing uploaded files
*/
Route::get($route.'/storage/profiles/{filename}', function ($filename)
{
    $path = storage_path('/app/profiles/' . $filename);

    if (!File::exists($path)) {
        abort(404);
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);

    return $response;
});
Route::get($route.'/storage/logo/{filename}', function ($filename)
{
    $path = storage_path('/app/logos/' . $filename);

    if (!File::exists($path)) {
        abort(404);
    }

    $file = File::get($path);
    $type = File::mimeType($path);

    $response = Response::make($file, 200);
    $response->header("Content-Type", $type);

    return $response;
});

Route::get('/cache', function () {
    $exitCode = Artisan::call('config:cache');
    return 'hey'.$exitCode;

    //
});
Route::get('/clear', function () {
    $exitCode = Artisan::call('config:cache');
    return 'hey'.$exitCode;

    //
});
Route::get('/migrate', function () {
    $exitCode = Artisan::call('migrate');
    return 'hey'.$exitCode;

    //
});

/* Authentication Router */
$route = env('PACKAGE_ROUTE', '').'/authenticate';
Route::resource($route, 'AuthenticateController', ['only' => ['index']]);
Route::post($route, 'AuthenticateController@authenticate');
Route::post($route.'/user', 'AuthenticateController@getAuthenticatedUser');
Route::post($route.'/refresh', 'AuthenticateController@refreshToken');
Route::post($route.'/invalidate', 'AuthenticateController@deauthenticate');
Route::post($route.'/auth', function () {
    return true;
});

// Google Place
$route = env('PACKAGE_ROUTE', '').'/google_places/';
$controller = 'GooglePlaceController@';
Route::post($route.'search', $controller."search");

//Emails Controller
$route = env('PACKAGE_ROUTE', '').'/emails';
Route::post($route.'/create', "EmailController@create");
Route::post($route.'/retrieve', "EmailController@retrieve");
Route::post($route.'/update', "EmailController@update");
Route::post($route.'/delete', "EmailController@delete");
Route::post($route.'/reset_password', 'EmailController@resetPassword');
Route::post($route.'/verification', 'EmailController@verification');
Route::post($route.'/changed_password', 'EmailController@changedPassword');
Route::post($route.'/referral', 'EmailController@referral');
Route::post($route.'/trial', 'EmailController@trial');
Route::post($route.'/test_sms', 'EmailController@testSMS');

//Notification Settings Controller
$route = env('PACKAGE_ROUTE', '').'/notification_settings/';
$controller = 'NotificationSettingController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update_otp', $controller."generateOTP");
Route::post($route.'update_otp_mobile', $controller."generateOTPMobile");
Route::post($route.'block_account', $controller."blockedAccount");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");
Route::get($route.'test', $controller.'test');

// Messenger Groups Custom
$route = env('PACKAGE_ROUTE', '').'/custom_messenger_groups/';
$controller = 'MessengerGroupController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_by_params', $controller."retrieveByParams");

// Requests
$route = env('PACKAGE_ROUTE', '').'/requests/';
$controller = 'RequestMoneyController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_item', $controller."retrieveItem");
Route::post($route.'retrieve_by_payload_value', $controller."retrieveByPayloadValue");
Route::post($route.'payments', $controller."payments");
Route::post($route.'manage_request_by_thread', $controller."manageRequestByThread");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// Bookmark
$route = env('PACKAGE_ROUTE', '').'/bookmarks/';
$controller = 'BookmarkController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// Ledger
$route = env('PACKAGE_ROUTE', '').'/ledgers/';
$controller = 'LedgerController@';
Route::post($route.'summary', $controller."summary");
Route::post($route.'dashboard', $controller."dashboard");
Route::post($route.'create_on_deposit', $controller."createOnDeposit");
Route::post($route.'create_on_withdrawal', $controller."createOnWithdrawal");

// Investment
$route = env('PACKAGE_ROUTE', '').'/investments/';
$controller = 'InvestmentController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// Report
$route = env('PACKAGE_ROUTE', '').'/reports/';
$controller = 'ReportController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// Payments
$route = env('PACKAGE_ROUTE', '').'/payments/';
$controller = 'PaymentController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");


// Education
$route = env('PACKAGE_ROUTE', '').'/educations/';
$controller = 'EducationController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// TestController
$route = env('PACKAGE_ROUTE', '').'/testing/';
$controller = 'TestController@';
Route::get($route.'testing', $controller."testing");
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// Penalties
$route = env('PACKAGE_ROUTE', '').'/penalties/';
$controller = 'PenaltyController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// Works
$route = env('PACKAGE_ROUTE', '').'/works/';
$controller = 'WorkController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// Certificates
$route = env('PACKAGE_ROUTE', '').'/certificates/';
$controller = 'CertificateController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");


//Withdraw
$route = env('PACKAGE_ROUTE', '').'/withdrawals/';
$controller = 'WithdrawController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

//Pulling of investors
$route = env('PACKAGE_ROUTE', '').'/pullings/';
$controller = 'PullingController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

//Guarantors
$route = env('PACKAGE_ROUTE', '').'/guarantors/';
$controller = 'GuarantorController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// Account Card Controller
$route = env('PACKAGE_ROUTE', '').'/account_cards/';  
$controller = 'AccountCardController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");

// Account Card Controller
$route = env('PACKAGE_ROUTE', '').'/transfer_charges/';  
$controller = 'TransferChargeController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");

//Request Peers Controller
$route = env('PACKAGE_ROUTE', '').'/request_peers/';  
$controller = 'RequestPeerController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'retrieve_item', $controller."retrieveItem");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");

//Request Validations Controller
$route = env('PACKAGE_ROUTE', '').'/request_validations/';  
$controller = 'RequestValidationController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'update', $controller."update");
Route::post($route.'delete', $controller."delete");

//Coupon Controller
$route = env('PACKAGE_ROUTE', '').'/coupons/';
$controller = 'CouponController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'validate', $controller."retrieveByValidation");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");


//Account Coupon Controller
// $route = env('PACKAGE_ROUTE', '').'/account_coupons/';
// $controller = 'WithdrawController@';
// Route::post($route.'create', $controller."create");
// Route::post($route.'retrieve', $controller."retrieve");
// Route::post($route.'delete', $controller."delete");
// Route::post($route.'update', $controller."update");

//Google Places Controller
// $route = env('PACKAGE_ROUTE', '').'/google_places/';
// $controller = 'GooglePlaceController@';
// Route::post($route.'search', $controller."search");


// MyCircles Controller
$route = env('PACKAGE_ROUTE', '').'/my_circle/';
$controller = 'MyCircleController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'validate', $controller."retrieveByValidation");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");

// MyCircles Controller
$route = env('PACKAGE_ROUTE', '').'/my_circles/';
$controller = 'MyCircleController@';
Route::post($route.'create', $controller."create");
Route::post($route.'retrieve', $controller."retrieve");
Route::post($route.'validate', $controller."retrieveByValidation");
Route::post($route.'delete', $controller."delete");
Route::post($route.'update', $controller."update");


// MyCircles Controller
$route = env('PACKAGE_ROUTE', '').'/fcm/';
$controller = 'FirebaseController@';
Route::post($route.'send', $controller."send");