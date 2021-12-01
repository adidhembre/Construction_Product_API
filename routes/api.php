<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\APIController;
use App\Http\Controllers\productSearch;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
//Final Routes for API
Route::get('/listing/{plid}',[APIController::class,'listing']);
Route::get('/project/{pid}',[APIController::class,'project']);
Route::get('/records/{plid}',[APIController::class,'records']);

//final Route for Analytics API
Route::get('/product-search/',[productSearch::class,'product']);
Route::get('/product-search/filter-count/{filter}',[productSearch::class,'filterCount']);
Route::get('/product-search/summary',[productSearch::class,'summary']);
/* sample seach url
http://127.0.0.1:8000/api/product-search?filters[state]=Madhya%20Pradesh&filters[project_value]=19-25&columns[]=state&columns[]=average_area
*/