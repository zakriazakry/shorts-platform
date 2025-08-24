<?php

use App\Http\Controllers\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/create-short', [VideoController::class, 'createShorts']);
