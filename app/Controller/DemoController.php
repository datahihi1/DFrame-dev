<?php

namespace App\Controller;

use Core\Application\Router;
use Core\Application\View;
use Gregwar\Captcha\CaptchaBuilder;

class DemoController extends Controller
{

    #[Router(path: '/haha', method: 'GET', isApi: false, name: 'demo.haha', middleware: null)]
    public function demo()
    {
        return "oke";
    }
    #[Router(path: '/captcha', method: 'GET', name: 'show.captcha')]
    public function captcha()
    {
        $builder = new CaptchaBuilder();
        $builder->build();

        // Lưu mã CAPTCHA vào session
        $_SESSION['captcha'] = $builder->getPhrase();

        return View::render('captcha', ['builder' => $builder]);
    }
    #[Router(path: '/verify-captcha', method: 'POST', name: 'verify.captcha')]
    public function verifyCaptcha()
    {
        $captcha = $_POST['captcha'] ?? '';
        if ($captcha === $_SESSION['captcha']) {
            // CAPTCHA đúng
            return "CAPTCHA đúng";
        } else {
            // CAPTCHA sai
            return "CAPTCHA sai";
        }
    }
}
