<?php



use App\com_pinoox_app\Controller\MainController;
use function Pinoox\Router\get;



get('/')->action([MainController::class, 'index'])->name('home');

