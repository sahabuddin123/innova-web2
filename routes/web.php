<?php
use App\Http\Controllers\BkashController;
use App\Http\Controllers\Frontend\AssetController;
use App\Models\Client;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\TechnologyController;
use App\Http\Controllers\Admin\TeamController;
require 'admin.php';
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

Route::get('/', 'Frontend\FrontendController@index')->name('frontend.home');

// Route::get('/v2', 'Frontend\FrontendController@indexv2')->name('frontend.home2');


Route::get('{id}/admission', 'Frontend\FrontendController@courseAdmission')->name('frontend.course.admission');
Route::post('/admissions', 'Frontend\FrontendController@storeAdmission')->name('courses.admission.store');
Route::get('/about', 'Frontend\FrontendController@about')->name('frontend.about');
Route::get('/asset-registration', [AssetController::class, 'index'])->name('asset.index');
Route::post('/asset-registration-submit', [AssetController::class, 'store'])->name('asset.store');
Route::get('/team', 'Frontend\FrontendController@team')->name('frontend.team');
Route::get('/contact', 'Frontend\FrontendController@contact')->name('frontend.contact');
Route::post('/contact', 'Frontend\FrontendController@storeContact')->name('contact.store');
Route::get('/emp/{id}', 'Frontend\FrontendController@getEmployeeInfo')->name('emp.info');
Route::get('/std/{id}', 'Frontend\FrontendController@getStudentInfo')->name('std.info');
Route::get('/course-categories', 'Frontend\FrontendController@courseCategories')->name('frontend.course.categories');
Route::get('/404', 'Frontend\FrontendController@pageNotFound')->name('frontend.404');
Route::get('/privacy-policy', 'Frontend\FrontendController@privacyPolicy')->name('frontend.privacy.policy');
Route::get('/terms-conditions', 'Frontend\FrontendController@termsConditions')->name('frontend.terms.conditions');
Route::get('/certifiocate-verify/{slnum}', 'Frontend\FrontendController@certificateVerify')->name('frontend.certificate.verify');
Route::get('/certifiocate-verify-bteb', 'Frontend\FrontendController@certificateVerifyForm')->name('frontend.certificate.verify.form');
Route::post('/certifiocate-verify-bteb', 'Frontend\FrontendController@certificateVerifyCheck')->name('frontend.certificate.verify.check');
//seminor
Route::get('/seminors', 'Frontend\FrontendController@Seminors')->name('frontend.seminors');
Route::get('/seminorsreg/{id}', 'Frontend\FrontendController@SeminorsReg')->name('frontend.seminors.reg');
Route::post('/seminorsregstration', 'Frontend\FrontendController@SeminorsRegStore')->name('frontend.seminors.store');
//blogs
Route::get('/blogs', 'Frontend\FrontendController@blogs')->name('frontend.blogs');
Route::get('/blogs/{id}', 'Frontend\FrontendController@blogsShow')->name('frontend.blogs.read');
Route::post('/searchblog', 'Frontend\FrontendController@search')->name('frontend.blogs.searchblog');
//success Stroy
Route::get('/successstory', 'Frontend\FrontendController@successStory')->name('frontend.successstory');
//frelanching
Route::get('/frelanching', 'Frontend\FrontendController@frelanching')->name('frontend.frelanching');

// Services, Products, Concerns, Workflow
Route::get('/services', 'FrontendServiceController@index')->name('frontend.services');
Route::get('/products', 'FrontendProductController@index')->name('frontend.products');
Route::get('/concerns', 'FrontendConcernController@index')->name('frontend.concerns');
Route::get('/workflow', 'FrontendWorkflowController@index')->name('frontend.workflow');
// course search by category
Route::get('/courses/{id}', 'Frontend\FrontendController@CoursesByCategory')->name('frontend.courses.category');
Route::get('/course-details/{id}', 'Frontend\FrontendController@courseDetails')->name('frontend.course.details');

Route::get('/courses', 'Frontend\FrontendController@courses')->name('frontend.courses');
//faq
Route::get('/faq', 'Frontend\FrontendController@faq')->name('frontend.faq');
//Instructor
Route::get('/instructors', 'Frontend\FrontendController@instructors')->name('frontend.instructors');
Route::get('/instructors/{id}', 'Frontend\FrontendController@instructorDetails')->name('frontend.instructors.details');
//galary
Route::get('/gallery', 'Frontend\FrontendController@gallery')->name('frontend.galary');
//btecb registration
Route::group(['prefix'  =>  '/frontend'], function () {
    Route::get('/{id}/session', 'Frontend\FrontendController@sessions')->name('frontend.session.view');
    Route::get('/{id}/registration', 'Frontend\FrontendController@registerlist')->name('frontend.registration.view');

});


Auth::routes();
Route::get('logout', 'Auth\LoginController@logout')->name('user.logout');
Route::get('/home', 'HomeController@index')->name('home');

Route::group(['prefix' => 'user'], function () {
    Route::get('/dashboard', 'UserController@index')->name('user.dashboard');
    Route::get('/profile', 'UserController@profile')->name('users.profile');
    Route::get('/payment-history', 'UserController@paymentHistory')->name('user.payment.history');
});
Route::group(['prefix' => 'registration'], function () {
    Route::get('/{id}/create', 'UserController@RegistrationCreate')->name('users.registration.create');
    Route::post('/store', 'UserController@RegistrationStore')->name('users.registration.store');
    Route::get('/req-payment', 'UserController@RegPayment')->name('user.reg.payment');
});




// Checkout IFrame User
Route::get('/pay', [BkashController::class, 'pay'])->name('pay');
Route::get('/user/bkash/create', [BkashController::class, 'create'])->name('create.bkash');
Route::get('/user/bkash/execute', [BkashController::class, 'execute'])->name('execute.bkash');

Route::get('/user/success', [BkashController::class, 'success'])->name('success.bkash');
Route::get('/user/fail', [BkashController::class, 'fail'])->name('fail.bkash');

Route::get('/user/bkash/search', [BkashController::class, 'search'])->name('search.bkash');
Route::get('/user/bkash/search-transaction', [BkashController::class, 'searchTransaction'])->name('search-transaction.bkash');

// Checkout IFrame Refund Admin 

// Dev-only: test route to render `categoryDetails` with synthetic data for pagination testing
if (env('APP_DEBUG')) {
    Route::get('/dev/test-category-print', function() {
        $processedExpenses = [];
        // generate 50 sample rows
        for ($i = 1; $i <= 50; $i++) {
            $day = ($i % 30) + 1;
            $month = 8;
            $amount = rand(100, 50000);
            $processedExpenses[] = [
                'id' => $i,
                'invoiceDate' => date('Y-m-d'),
                'day' => str_pad($day, 2, '0', STR_PAD_LEFT),
                'month' => str_pad($month, 2, '0', STR_PAD_LEFT),
                'description' => 'টেস্ট পে-আইটেম ' . $i,
                'amount' => (float)$amount,
                'amountFormatted' => number_format((float)$amount, 2),
            ];
        }

        $totalExpense = array_sum(array_column($processedExpenses, 'amount'));
        $totalFormatted = number_format($totalExpense, 2);
        $month = 8; $year = date('Y');
        $banglaMonths = [1=>'জানুয়ারী',2=>'ফেব্রুয়ারী',3=>'মার্চ',4=>'এপ্রিল',5=>'মে',6=>'জুন',7=>'জুলাই',8=>'আগস্ট',9=>'সেপ্টেম্বর',10=>'অক্টোবর',11=>'নভেম্বর',12=>'ডিসেম্বর'];
        $monthName = $banglaMonths[$month];
        $serialNo = '19';
        $branchName = 'দোকান-১';
        $branchAddress = '';
        $totalInWords = '';

        return view('admin.report.categoryDetails', compact('processedExpenses'))
            ->with('category', (object)['categoris' => 'টেস্ট ক্যাটাগরি'])
            ->with('totalExpense', $totalExpense)
            ->with('totalFormatted', $totalFormatted)
            ->with('month', $month)
            ->with('year', $year)
            ->with('monthName', $monthName)
            ->with('serialNo', $serialNo)
            ->with('branchName', $branchName)
            ->with('branchAddress', $branchAddress)
            ->with('totalInWords', $totalInWords)
            ;
    });
}

// React Foundation Verification Route (Phase 3.0 Setup Only)
Route::get('/react-health', function () {
    return view('react-health');
})->name('react.health');

Route::prefix('admin')->name('admin.')->middleware(['auth:admin'])->group(function () {
    Route::resource('clients', 'Admin\\ClientController');
    Route::resource('technologies', 'Admin\\TechnologyController');
    Route::resource('teams', 'Admin\\TeamController');
});

