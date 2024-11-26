<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// routes/api.php
use App\Http\Controllers\Apis\UserController;
use App\Http\Controllers\Apis\DepartmentController;
use App\Http\Controllers\Apis\TeamController;
use App\Http\Controllers\Apis\LegalSecretaryController;
use App\Http\Controllers\Apis\OtpController;
use App\Http\Controllers\Apis\WebContentController;
use App\Http\Controllers\Apis\ServicesController;
use App\Http\Controllers\Apis\NewsController;
use App\Http\Controllers\Apis\GalleryController;
use App\Http\Controllers\Apis\EventController;
use App\Http\Controllers\Apis\FaqLawController;
use App\Http\Controllers\Apis\AppContentController;
use App\Http\Controllers\Apis\BookingController;
use App\Http\Controllers\Apis\TimeSlotController;
use App\Http\Controllers\Apis\ServiceCategoryController;
use App\Http\Controllers\Apis\ReviewController;
use App\Http\Controllers\Apis\ElementController;
use App\Http\Controllers\Apis\QuoteController;
use App\Http\Controllers\Apis\CaseManagementController;

use App\Http\Controllers\Apis\NotificationController;

Route::post('/send-notification', [NotificationController::class, 'sendNotification']);


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
// Client register route
Route::post('register', [UserController::class, 'register'])->name('register');
Route::post('login', [UserController::class, 'login'])->name('login');

// Send OTP to user
Route::post('sendOtp', [OtpController::class, 'sendOtp']);
// OTP Verification
Route::post('/verifyOtp',  [OtpController::class, 'verifyOtp']);
// Update new password
Route::post('/updatePassword', [OtpController::class, 'updatePassword']);

// For Admin
Route::group(['prefix' => 'admin'], function () {
    Route::post('login', [UserController::class, 'login'])->name('login');
});


// Booking Apis
Route::group(['prefix' => 'booking', 'middleware' => 'validateLang'], function() {    
    // Book a Meeting Process
    Route::post('/meetingStore/{lang?}', [BookingController::class, 'meetingStore'])->name('register');
    
    // Booking List by Status
    Route::post('/meetingList/{lang?}/{per_page_count?}', [BookingController::class, 'meetingList'])->name('list');
    
    // Booking List filter
    Route::post('/meetingListSearch/{lang?}/{per_page_count?}', [BookingController::class, 'meetingListSearch'])->name('listSearch');
});

// Get quote Apis
Route::group(['prefix' => 'quote', 'middleware'=>'validateLang'], function() {
    Route::post('/sendmail/{lang}', [QuoteController::class,'createQuoteSendMail']);
    Route::get('/{lang}', [QuoteController::class,'fetchAll']);
});

// Fetch Time Slots
Route::post('/timeSlots', [TimeSlotController::class, 'fetchSlots']);

// Teams Get Data Apis
Route::group(['prefix' => 'teams', 'middleware' => 'validateLang'], function() {
    Route::get('/{lang?}/{per_page_count?}', [TeamController::class, 'index'])->name('list');
    Route::get('/singleDetail/{id}/{lang?}', [TeamController::class, 'show'])->name('fetch');
});

// Legal Secretary Get Data Apis
Route::group(['prefix' => 'legalSecretaries', 'middleware' => 'validateLang'], function() {
    Route::get('/{lang?}/{per_page_count?}', [LegalSecretaryController::class, 'index'])->name('list');
    Route::get('/singleDetail/{id}/{lang?}', [LegalSecretaryController::class, 'show'])->name('fetch');
});

// Department Get Data Apis
Route::group(['prefix' => 'departments', 'middleware' => 'validateLang'], function() {
    Route::get('/{lang?}/{per_page_count?}', [DepartmentController::class, 'index'])->name('list');
    Route::get('/singleDetail/{id}/{lang?}', [DepartmentController::class, 'show'])->name('fetch');
    Route::get('/fetchLawyers/{id}/{lang?}', [DepartmentController::class, 'fetchLawyers'])->name('fetchLawyers');
});

// Events Get Data Apis
Route::group(['prefix' => 'departments', 'middleware' => 'validateLang'], function() {
    Route::get('/{lang?}/{per_page_count?}', [DepartmentController::class, 'index'])->name('list');
    Route::get('/singleDetail/{id}/{lang?}', [DepartmentController::class, 'show'])->name('fetch');
    Route::get('/fetchLawyers/{id}/{lang?}', [DepartmentController::class, 'fetchLawyers'])->name('fetchLawyers');
});

// For Admin Service Resource 
Route::group(['prefix' => 'services', 'middleware' => 'validateLang'], function() {
    Route::get('/{lang?}/{per_page_count?}', [ServicesController::class, 'indexServices'])->name('list');
    Route::get('/singleDetail/{id}/{lang?}', [ServicesController::class, 'showService'])->name('fetch');
    Route::get('/fetchPageContent/{id}/{lang?}', [ServicesController::class, 'fetchPageContent'])->name('fetchPageContent');
});

// social links get apis 
Route::group(['prefix' => 'admin', 'middleware' => 'validateLang'], function() {
    Route::get('/setting/social', [UserController::class, 'getSocialMediaLinks'])->name('fetchSocialMediaLinks');
    Route::get('/setting/get-whatsapp', [UserController::class, 'getWhatsAppLinks']);
});

// review section get apis 
Route::group(['prefix' => 'reviews', 'middleware' => 'validateLang'], function() {
    Route::get('/index/{lang}/{per_page_count?}', [ReviewController::class, 'index'])->name('list');
    Route::get('/review/{lang}/{id}', [ReviewController::class, 'getReview'])->name('fetch');
});

// elements section get apis 
Route::group(['prefix' => 'elements', 'middleware' => 'validateLang'], function() {
    Route::get('/element/{lang}', [ElementController::class,'getElements'])->name('fetch');
});

// WebContents Get Data Apis
Route::group(['prefix' => 'webContents', 'middleware' => 'validateLang'], function() {
    
    Route::get('/combineContent/{lang}', [WebContentController::class,'combineContent']);
    
    /* Home content controller */
    // Route::post('/home/{lang}', [WebContentController::class, 'createOrUpdateWebHome']);
    Route::get('/home/{lang}', [WebContentController::class,'getWebHomeContent'])->name('home');
    Route::get('/home/pagecontent/{id}/{lang}', [WebContentController::class, 'fetchHomePageContent'])->name('fetchHome');
    
    //About Us content
    Route::get('/aboutUs/{lang?}', [WebContentController::class, 'getWebAboutUsContent'])->name('aboutUs');
    
    /* Gallery content */
    Route::post('/gallery/{lang}', [WebContentController::class, 'createOrUpdateGallery']);
    Route::get('/gallery/{lang}', [WebContentController::class,'getGalleryContent'])->name('fetchGallery');
    
    // Contact us content
    Route::post('/contact/{lang}', [WebContentController::class, 'createOrUpdateWebContact']);
    Route::get('/contact/{lang}', [WebContentController::class,'getWebContactUsContent'])->name('fetchContactUs');
    
    /* News/Event/Services/FAQs/Law content controller */
    Route::post('/metadata/{slug}/{lang}', [WebContentController::class, 'createUpdateMetaData']);
    Route::get('/metadata/{slug}/{lang}', [WebContentController::class, 'getWebMetaDeta'])->name('webMetaDeta');
    
    // Other tab content controller
    Route::get('/othertab/{slug}/{lang}', [WebContentController::class, 'indexOtherTab'])->name('fetchOtherTab');
    
    // Privacy policy 
    Route::get('/privacy_policy/{lang}', [WebContentController::class,'indexWebPrivacyContent'])->name('fetchPrivacyPolicy');
    
    /* T&C content controller */
    Route::get('/terms_conditions/{lang}', [WebContentController::class,'indexWebTermsConditonsContent'])->name('fetchTermsConditions');
});

// appContents Get Data Apis
Route::group(['prefix' => 'appcontent'], function () {
    // App content controller on-boarding
    Route::get('/onboard', [AppContentController::class, 'getOnBoardingContent'])->name('onboard');

    // App content controller t&c
    Route::get('/term-condition', [AppContentController::class, 'getTCContent'])->name('term-condition');

    // App content controller privacy policy
    Route::get('/privacy-policy', [AppContentController::class, 'getPrivacyPolicyContent'])->name('privacy-policy');
});

// FAQs/Laws  Get Data Apis
Route::group(['prefix' => 'faqlaw','middleware' => 'validateLang'], function(){
    /* Faq routes */
    Route::get('/faq/{lang}',[FaqLawController::class, 'faqIndex'])->name('faqs-list');

    /* Law routes */
    Route::get('/law/{lang}',[FaqLawController::class, 'lawIndex'])->name('laws-list');
});

// For News Get Data Apis
Route::group(['prefix' => 'news', 'middleware' => 'validateLang'], function(){
    Route::get('/index/{lang}/{per_page_count?}', [NewsController::class, 'index'])->name('list');
    Route::get('/fetch/{slug}/{lang}', [NewsController::class, 'getNews'])->name('fetch');
});

// For Mobile Articles Get Data Apis
Route::group(['prefix' => 'mobileArticles', 'middleware' => 'validateLang'], function(){
    Route::get('/list/{lang}/{per_page_count?}', [NewsController::class, 'mobileArticleList'])->name('mobileArticleList');
    Route::get('/singleDetail/{slug}/{lang}', [NewsController::class, 'singleArticleDetail'])->name('singleArticleFetch');
});

// For Events Get Data Apis
Route::group(['prefix' => 'events', 'middleware' => 'validateLang'], function(){
    Route::get('/index/{lang}/{per_page_count?}', [EventController::class, 'index'])->name('list');
    Route::get('/fetch/{slug}/{lang}', [EventController::class, 'getEvent'])->name('fetch');
});

// For Mobile Events Get Data Apis
Route::group(['prefix' => 'mobileEvents', 'middleware' => 'validateLang'], function(){
    Route::get('/list/{lang}/{per_page_count?}', [EventController::class, 'mobileEventsList'])->name('mobileEventsList');
    Route::get('/singleDetail/{slug}/{lang}', [EventController::class, 'singleEventDetail'])->name('singleEventFetch');
});

// Services post api
Route::group(['prefix' => 'servicescategory', 'middleware' => 'validateLang'], function() {
    //services category get apis
    Route::get('/service_categories/{lang}', [ServiceCategoryController::class, 'getServiceCategories'])->name('list');
    Route::get('/service_categories/{lang}/{id}', [ServiceCategoryController::class, 'getCategoryById'])->name('fetch');
    Route::get('/services_relates_category/{lang}', [ServiceCategoryController::class, 'fetchServicesRelatesCategory'])->name('fetchServices');
});


Route::middleware(['auth:api', 'jwt.expired'])->group(function () {
    //User Update Profile
    Route::post('updateProfile', [UserController::class, 'updateProfile']);
    
    // Notification Permission
    Route::post('notificationStatus', [UserController::class, 'notificationStatus']);
    
    // Get Notification Status
    Route::get('getNotificationStatus', [UserController::class, 'getNotificationStatus']);
    
    // User Logout
    Route::post('logout', [UserController::class, 'logout']);
    
    // Change Password
    Route::post('changePassword', [UserController::class, 'changePassword']);
    
    // User Delete Account
    Route::get('getProfile', [UserController::class, 'getProfile']);
    
    // User Get Profile
    Route::delete('deleteAccount', [UserController::class, 'deleteAccount']);
    
    // settings social/whatsapp post api
    Route::group(['prefix' => 'admin', 'middleware' => 'validateLang'], function() {
        Route::post('/setting/social', [UserController::class, 'createOrUpdateSocialLinks']);
        Route::delete('/setting/social/{id}', [UserController::class, 'deleteSocialMediaLink']);
        Route::post('/setting/create-whatsapp', [UserController::class,'createOrUpdateWhatsApp']);
    });
    
    // review post api
    Route::group(['prefix' => 'reviews', 'middleware' => 'validateLang'], function() {
        Route::post('/review/{lang}', [ReviewController::class, 'createReview']);
        Route::post('/review/{lang}/{id}', [ReviewController::class, 'updateReview']);
        Route::delete('/review/{id}', [ReviewController::class, 'removeReview']);
    });
    
    // elements section post apis 
    Route::group(['prefix' => 'elements', 'middleware' => 'validateLang'], function() {
        Route::post('/element/{lang}', [ElementController::class,'createOrUpdateElement']);
    });
    
    // App content routes/controller
    Route::group(['prefix' => 'appcontent'], function () {
        // App content controller on-boarding
        Route::post('/onboard', [AppContentController::class, 'createOrUpdateOnBoarding']);
       
        // App content controller t&c
        Route::post('/term-condition', [AppContentController::class, 'createOrUpdatTC']);
       
        // App content controller privacy policy
        Route::post('/privacy-policy', [AppContentController::class, 'createOrUpdatePrivacyPolicy']);
    });
    
    
    // Faq/Laws routes/controller
    Route::group(['prefix' => 'faqlaw','middleware' => 'validateLang'], function(){
        /* Faq routes */
        Route::post('/faq/{lang}',[FaqLawController::class, 'createOrUpdateFaq']);

        /* Law routes */
        Route::post('/law/{lang}',[FaqLawController::class, 'createOrUpdateLaw']);
    });
    
    // For Admin Team Resource 
    Route::group(['prefix' => 'teams', 'middleware' => 'validateLang'], function() {
        Route::post('/{lang?}', [TeamController::class, 'store']);
        Route::post('/{id}/{lang?}', [TeamController::class, 'update']);
        Route::delete('/{id}', [TeamController::class, 'destroy']);
        
    });
    
    // For Admin Team Resource 
    Route::group(['prefix' => 'legalSecretaries', 'middleware' => 'validateLang'], function() {
        Route::post('/{lang?}', [LegalSecretaryController::class, 'store']);
        Route::post('/{id}/{lang?}', [LegalSecretaryController::class, 'update']);
        Route::delete('/{id}', [LegalSecretaryController::class, 'destroy']);
        
    });
    
    Route::group(['prefix' => 'searchlist', 'middleware' => 'validateLang'], function() {
        Route::post('/search_team_list/{lang}/{per_page_count?}', [TeamController::class,'searchTeamList']);
        Route::post('/search_secretary_list/{lang}/{per_page_count?}', [LegalSecretaryController::class,'searchSecretaryList']);
        Route::post('/search_service_list/{lang}/{per_page_count?}', [ServicesController::class,'searchServices']);
        Route::post('/search_department_list/{lang}/{per_page_count?}', [DepartmentController::class,'searchDepartmentList']);
        Route::post('/search_review_list/{lang}/{per_page_count?}', [ReviewController::class,'searchReviewList']);
        
        Route::post('/search_news_list/{lang}/{per_page_count?}', [NewsController::class,'searchNewsList']);
        Route::post('/search_event_list/{lang}/{per_page_count?}', [EventController::class,'searchEventList']);
    });
    
    // Dashboard routes
    Route::group(['prefix' => 'dashboard', 'middleware' => 'validateLang'], function() {
        Route::get('/bookings/{lang}', [BookingController::class, 'getLatestBookings']);
    });
    
    Route::group(['prefix' => 'teamorder'], function() {
        Route::post('/updateorder', [TeamController::class,'updateOrderNumber']);
    });
    
    
    
    // For Admin Department Resource
    Route::group(['prefix' => 'departments', 'middleware' => 'validateLang'], function() {
        Route::post('/{lang?}', [DepartmentController::class, 'store']);
        Route::post('/{id}/{lang?}', [DepartmentController::class, 'update']);
        Route::delete('/{id}', [DepartmentController::class, 'destroy']);
    });
    
    // For news section
    Route::group(['prefix' => 'news', 'middleware' => 'validateLang'], function(){
        Route::post('/create/{lang}', [NewsController::class, 'storeNews']);
        Route::post('/update/{id}/{lang}', [NewsController::class, 'updateNews']);
        Route::delete('/delete/{id}', [NewsController::class, 'deleteNews']);
    });
    
    // For event section
    Route::group(['prefix' => 'events', 'middleware' => 'validateLang'], function(){
        Route::post('/create/{lang}', [EventController::class, 'storeEvent']);
        Route::post('/update/{id}/{lang}', [EventController::class, 'updateEvent']); // change method to post if put method is not working
        Route::delete('/delete/{id}', [EventController::class, 'deleteEvent']);
    });
    
    // Dashboard routes
    Route::group(['prefix' => 'dashboard', 'middleware' => 'validateLang'], function() {
        Route::get('/bookings/{lang}', [BookingController::class, 'getLatestBookings']);
        Route::get('/departments/{lang}', [DepartmentController::class, 'getDepartments']);
        Route::get('/teams/{lang}', [TeamController::class, 'getTeams']);
    });
    
    // Web Content Resource
    Route::group(['prefix' => 'webContents', 'middleware' => 'validateLang'], function() {
        
        //Home content
        Route::post('/home/{lang}', [WebContentController::class,'createOrUpdateWebHome']);
        
        /* Gallery content */
        Route::post('/gallery/{lang}', [WebContentController::class,'createOrUpdateGallery']);
        
        // Contact us content
        Route::post('/contact/{lang}', [WebContentController::class,'createOrUpdateWebContact']);
        
        // About Us Routes
        Route::post('/aboutUs/{lang?}', [WebContentController::class, 'saveOrUpdateWebAboutUsContent']);
        
        // Privacy policy 
        Route::post('/privacy_policy/{lang}', [WebContentController::class,'createOrUpdatePrivacyPolicy']);
        
        /* T&C content controller */
        Route::post('/terms_conditions/{lang}', [WebContentController::class,'createOrUpdateTermsConditions']);
        
        /* Other tab content controller */
        Route::post('/othertab/{slug}/{lang}', [WebContentController::class, 'createUpdateOtherTab']);
    });
    
    // For Admin Service Resource 
    Route::group(['prefix' => 'services', 'middleware' => 'validateLang'], function() {
        Route::post('/{lang?}', [ServicesController::class, 'storeService']);
        Route::post('/{id}/{lang?}', [ServicesController::class, 'updateService']);
        Route::delete('/{id}', [ServicesController::class, 'destroyService']);
    });
    Route::post('service_sections/remove-inner-object', [ServicesController::class, 'removeInnerObject']);
    
    Route::group(['prefix' => 'services_category', 'middleware' => 'validateLang'], function() {
        // For Service Category
        Route::post('/servicecategories/{lang}', [ServiceCategoryController::class, 'createServiceCategory']);
        Route::post('/servicecategories/{lang}/{id}', [ServiceCategoryController::class, 'updateServiceCategory']);
        Route::delete('/servicecategories/{id}', [ServiceCategoryController::class, 'deleteServiceCategory']);
    });
    
    // For Admin Booking Apis
    Route::group(['prefix' => 'booking', 'middleware' => 'validateLang'], function() {    
        // Book a Meeting Process
        Route::post('/meetingStatus/{id}/{lang?}', [BookingController::class, 'meetingStatus']);
        
        // Get Booking Notification History For Mobile App
        Route::get('notificationHistory/{id}', [NotificationController::class, 'notificationHistory']);
        
        // Booking notification status change from unread to read
        Route::post('/notificationMessageStatus', [NotificationController::class, 'notificationMessageStatus']);
        
        // Get Consultant List for Booking form
        Route::get('/consultantsList/{lang}', [BookingController::class,'consultantsList']);
    });
    
    // For Admin Case Management Apis
    Route::group(['prefix' => 'caseManagements'], function(){
        Route::get('/userList', [CaseManagementController::class, 'getuserList'])->name('userList');
        Route::post('/searchCaseList/{per_page_count?}', [CaseManagementController::class,'searchCaseList']);
        Route::get('{per_page_count?}', [CaseManagementController::class, 'index'])->name('list');
        Route::post('/create', [CaseManagementController::class, 'store']);
        Route::get('/singleDetail/{id}', [CaseManagementController::class, 'edit'])->name('fetch');
        Route::post('/update/{id}', [CaseManagementController::class, 'update']); // change method to post if put method is not working
        Route::delete('/delete/{id}', [CaseManagementController::class, 'destroy']);
        
        
        Route::get('/caseUpdatesList/{per_page_count?}', [CaseManagementController::class,'caseUpdatesList']);
        Route::get('/clientInquiresList/{per_page_count?}', [CaseManagementController::class,'clientInquiresList']);
        Route::get('/singleInquiryDetail/{id}', [CaseManagementController::class, 'singleInquiryDetail'])->name('singleInquiryDetail');
        Route::post('/searchCaseUpdatesList/{per_page_count?}', [CaseManagementController::class,'searchCaseUpdatesList']);
        Route::post('/searchClientInquiresList/{per_page_count?}', [CaseManagementController::class,'searchClientInquiresList']);
        
        //Mobile app apis
        Route::get('allCases/{client_id?}', [CaseManagementController::class, 'clientAllCasesList']);
        Route::get('/singleCaseDetail/{client_id?}/{id?}', [CaseManagementController::class, 'clientCaseDetail']);
        Route::get('formCasesList/{client_id?}', [CaseManagementController::class, 'formCasesList']);
        Route::get('/formSingleCaseDetail/{client_id?}/{id?}', [CaseManagementController::class, 'forSingleCaseDetail']);
        Route::post('/requestCaseUpdate', [CaseManagementController::class, 'requestCaseUpdate']);
        Route::post('/requestCaseInquiry', [CaseManagementController::class, 'requestCaseInquiry']);
        
    });
    
});

