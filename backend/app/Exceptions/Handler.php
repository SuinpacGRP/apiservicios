<?php

namespace App\Exceptions;

use Exception;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     * Se capturan las excepciones para los tokens
     * Se capturan las excepciones para los metodo soportados por las rutas
     * Se capturan las Excepciones para status code
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        if($exception instanceof MethodNotAllowedHttpException ){
            //EL metodo [GET o POST] no es soportado por esta ruta
            return response()->json([
                'Error' => 'El metodo no es soportado pora esta ruta ',
                'Message' => $exception->getMessage()
            ], 400);
        }
        if ($exception instanceof TokenExpiredException) {
            #Token Expired
            return response()->json([
                'Error' => 'Por seguridad tu sesión ha sido cerrada. Vuelva iniciar sesión',
                'Status' => 'false',
                'Message' => $exception->getMessage()
            ], 400);
        } elseif ($exception instanceof TokenInvalidException) {
            return response()->json([
                'Error' => 'Token Invalido',
                'Status' => 'false',
                'Message' => $exception->getMessage()
            ], 400);
            #Token Invalido
        } elseif ($exception instanceof JWTException) {
            #Falta Token
            return response()->json([
                'Error' => 'Falta Token',
                'Status' => 'false',
                'Message' => $exception->getMessage()
            ], 400);
        }
        /*if ($exception->getStatusCode() == 500) {
            return response()->json([
                'Error' => '500',
                'Message' => $exception->getMessage()
            ], 500);
        }
        if ($exception->getCode() == 404) {
            return response()->json([
                'Error' => '404',
                'Message' => $exception->getMessage()
            ], 404);
        }*/
        return response()->json([
            'Error' => $exception->getCode(),
            'Message' => $exception->getMessage(),
            'File' => $exception->getFile(),
            'Line' => $exception->getLine()
            #'Trace' => $exception->getTraceAsString(),
        ], 500);

        return parent::render($request, $exception);
    }
    
}