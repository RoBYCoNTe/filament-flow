<?php

use Illuminate\Support\Facades\Route;
use RoBYCoNTe\FilamentFlow\Http\Controllers\FormulaCompletionsController;

Route::get('filament-flow/formula-completions', FormulaCompletionsController::class)
    ->name('filament-flow.formula-completions')
    ->middleware(['web', 'auth']);
