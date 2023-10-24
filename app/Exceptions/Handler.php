<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if($e instanceof HttpException) {
            if(in_array($e->getStatusCode(), [403,401 ])) {
                return response()->json([
                  'message' => 'unauthenticated',
                  'is_authenticated' => false
                ], $e->getStatusCode());
            }
        }
        
        return parent::render($request, $e);
    }
}
