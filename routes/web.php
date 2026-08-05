<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HarnessConnectionController;
use App\Http\Controllers\MemorySourceController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\ReminderController;
use App\Http\Controllers\TodoAnalysisController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\TodoStepController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::resource('workspaces', WorkspaceController::class)->except(['create', 'edit']);
Route::resource('projects', ProjectController::class)->except(['create', 'edit']);
Route::resource('todos', TodoController::class)->except(['create', 'edit']);
Route::resource('todo-steps', TodoStepController::class)->only(['store', 'update', 'destroy']);
Route::resource('reminders', ReminderController::class)->except(['create', 'edit', 'show']);

Route::get('harnesses', [HarnessConnectionController::class, 'index'])->name('harnesses.index');
Route::post('harnesses', [HarnessConnectionController::class, 'store'])->name('harnesses.store');
Route::post('harnesses/{harnessConnection}/sync', [HarnessConnectionController::class, 'sync'])->name('harnesses.sync');

Route::get('quests', [QuestController::class, 'index'])->name('quests.index');
Route::post('quests', [QuestController::class, 'store'])->name('quests.store');
Route::patch('quests/{quest}/approve', [QuestController::class, 'approve'])->name('quests.approve');
Route::post('quests/{quest}/dispatch', [QuestController::class, 'dispatch'])->name('quests.dispatch');
Route::patch('quests/{quest}/cancel', [QuestController::class, 'cancel'])->name('quests.cancel');

Route::post('todos/{todo}/decompose', [TodoAnalysisController::class, 'store'])->name('todos.decompose');
Route::patch('todos/{todo}/status', [TodoController::class, 'updateStatus'])->name('todos.status');
Route::patch('todos/{todo}/plan', [TodoController::class, 'updatePlan'])->name('todos.plan');
Route::patch('todo-steps/{todoStep}/status', [TodoStepController::class, 'updateStatus'])->name('todo-steps.status');
Route::patch('reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');

Route::get('memory-sources', [MemorySourceController::class, 'index'])->name('memory-sources.index');
Route::post('memory-sources/sync', [MemorySourceController::class, 'sync'])->name('memory-sources.sync');
Route::patch('memory-sources/{memorySource}', [MemorySourceController::class, 'update'])->name('memory-sources.update');
Route::post('memory-sources/{memorySource}/project', [MemorySourceController::class, 'storeProject'])->name('memory-sources.project');
Route::patch('memory-sources/{memorySource}/ignore', [MemorySourceController::class, 'ignore'])->name('memory-sources.ignore');
