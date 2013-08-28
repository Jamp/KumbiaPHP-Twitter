<?php

/**
*
* Modelo para Acceder al API de Twitter usando la
* librería de Matt Harris <matt@themattharris.com>
*
* @author Jaro Marval (Jamp) <jampgold@gmail.com>
*
*/

Load::lib('tmhOAuth/tmhOAuth');

class Twitter extends tmhOAuth
{

    /**
     * Modelo para acceder al API de Twitter
     */
    public function __construct()
    {
        $ini = Config::read('api', true);
        $twitter = $ini['twitter'];
        $this->config = array(
            'consumer_key'    => $twitter['consumer_key'],
            'consumer_secret' => $twitter['consumer_secret'],
            'user_agent'      => 'Twitter para KumbiaPHP 1.0Beta2',
        );

        parent::__construct($this->config);
    }

    /**
     * Obtener url para el callback de la autenticación en Twitter
     * @param  boolean $dropqs Eliminar query en la URL, por defecto True
     * @return String          Cadena con la URL para el Callback
     */
    public function php_self($dropqs=true) {
        $protocol = 'http';
        if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
           $protocol = 'https';
        } elseif (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == '443')) {
            $protocol = 'https';
        }

        $url = sprintf('%s://%s%s', $protocol, $_SERVER['SERVER_NAME'], $_SERVER['REQUEST_URI']);

        $parts = parse_url($url);

        $port = $_SERVER['SERVER_PORT'];
        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $path = @$parts['path'];
        $qs   = @$parts['query'];

        $port or $port = ($scheme == 'https') ? '443' : '80';

        if (($scheme == 'https' && $port != '443') || ($scheme == 'http' && $port != '80')) { $host = "$host:$port"; }
        $url = "$scheme://$host$path";
        if ( ! $dropqs)
            return "{$url}?{$qs}";
        else
            return $url;
    }

    /**
     * Extraer a un array los parametros de un query en la URL
     * @return Array Arreglo con parametros => valores
     */
    public function uri_params() {
        $url = parse_url($_SERVER['REQUEST_URI']);
        $params = array();
        // Verificamos que haya un query y silenciamos los errores sino los hay
        if (isset($url['query'])){
            foreach (explode('&', $url['query']) as $p) {
                list($k, $v) = explode('=', $p);
                $params[$k] =$v;
            }
        }
        return $params;
    }

    public function request_token() {
        $code = $this->apponly_request(array(
          'without_bearer' => true,
          'method' => 'POST',
          'url' => $this->url('oauth/request_token', ''),
          'params' => array(
            'oauth_callback' => $this->php_self(false),
          ),
        ));

        if ($code != 200) {
            Flash::error("Ha ocurrido un error de comunicación con Twitter. {$this->response['response']}");
            return;
        }

        // Almacenamos toda la respues en la sesión luego de la redirección
        $_SESSION['oauth'] = $this->extract_params($this->response['response']);

        // Verificación de confirmación del callback
        if ($_SESSION['oauth']['oauth_callback_confirmed'] !== 'true') {
            Flash::error('Solicitud no confirmada por Twitter, por ello no puedo continuar.');
        } else {
            $url = $this->url('oauth/authorize', '') . "?oauth_token={$_SESSION['oauth']['oauth_token']}";
            return $url;
        }
    }


    /**
     * Obtener tokens a la cuenta de Twitter
     * @return Boolean/Array array(user_token, secret_token)
     */
    function access_token() {
        $params = $this->uri_params();

        // Verificamos que solo estemos haciendo una sola verificación
        if ($params['oauth_token'] !== $_SESSION['oauth']['oauth_token']) {
            Flash::error('El token OAuth con el que comenzo no coincide con el de la redirección. ¿Tiene más de una ventana/pestaña abierta?');
            session_unset();
            return False;
        }

        // Verificar que se nos haya concedido el acceso a nuestra aplicación
        if (!isset($params['oauth_verifier'])) {
            Flash::error('el "oauth verifier" no se encuentra por ello no podemos continuar. ¿Usted a negado el acceso a la aplicación?');
            session_unset();
            return False;
        }

        // Modificamos la configuración para usar los token temporales
        $this->reconfigure(array_merge($this->config, array(
            'token'  => $_SESSION['oauth']['oauth_token'],
            'secret' => $_SESSION['oauth']['oauth_token_secret'],
        )));

        // Ahora convertido a token permanentes
        $code = $this->user_request(array(
            'method' => 'POST',
            'url' => $this->url('oauth/access_token', ''),
            'params' => array(
              'oauth_verifier' => trim($params['oauth_verifier']),
            )
        ));

        if ($code == 200) {
            $oauth_creds = $this->extract_params($this->response['response']);
            return array(
                'user_token' => $oauth_creds['oauth_token'],
                'secret_token' => $oauth_creds['oauth_token_secret']
            );
        }

    }

    /**
     * Solicitar datos del usuario a Twitter
     * @param  String $user_token   Token del usuario
     * @param  String $secret_token Token secreto del usuario
     * @return Object               Objeto con toda la información de usuario en Twitter
     */
    public function profile($user_token, $secret_token){
        $this->reconfigure(array_merge($this->config, array(
            'token'  => $user_token,
            'secret' => $secret_token,
        )));

        $code = $this->user_request(array(
            'method' => 'GET',
            'url' => $this->url('1.1/account/verify_credentials','json')
            )
        );

        $response = json_decode($this->response['response']);
        if ($code != 200) {
            Flash::error("Ha ocurrido un error de comunicación con Twitter. {$response->errors[0]->message}");
            return False;
        }

        return $response;
    }

    /**
     * Tuitear desde nuestra app
     * @param  String $user_token   Token del usuario
     * @param  String $secret_token Token secreto del usuario
     * @param  String $text         Texto del Tweet
     * @return Boolean              True o False según
     */
    public function tweet($user_token, $secret_token, $text){

        $this->reconfigure(array_merge($this->config, array(
            'token'  => $user_token,
            'secret' => $secret_token,
        )));

        $code = $this->user_request(array(
            'method' => 'POST',
            'url' => $this->url('1.1/statuses/update', 'json'),
            'params' => array(
                    'status' => $text
                )
            )
        );

        $response = json_decode($this->response['response']);
        if ($code == 200) {
            return True;
        } else {
            Flash::error("Ha ocurrido un error de comunicación con Twitter. {$response->errors[0]->message}");
            return False;
        }
    }


    public function search($query) {

        $code = $this->apponly_request(array(
            'url' => $this->url('1.1/search/tweets'),
            'params' => array(
              'q' => str_replace('%7E', '~', rawurlencode($query))
            )
        ));

        $response = json_decode($this->response['response']);
        if ($code == 200) {
            return $response;
        } else {
            Flash::error("Ha ocurrido un error de comunicación con Twitter. {$response->errors[0]->message}");
            return False;
        }
    }

}
?>