<?php

/**
* Controlador ejemplo de operaciones minimas con Twitter
*
* @author Jaro Marval (Jamp) <jampgold@gmail.com>
*
*/
class CuentaController extends AppController
{
    // Tokens de Acceso para que la aplicacón pueda acceder a la cuenta de usuario
    // Son necesarias para obtener la data de usuario y tuitear
    private $user_token = '';
    private $secret_token = '';

    /**
     * Obtener tokens de acceso para la cuenta del usuario
     */
    public function index()
    {
        $twitter = Load::model('twitter');
        $params = $twitter->uri_params();
        if (!isset($params['oauth_token'])) {
            $this->url = $twitter->request_token();
        } else {
            $this->cuenta = $twitter->access_token();
            View::select('asociada');
        }

    }

    /**
     * Buscar en Twitter
     */
    public function search($query=null){
        if( Input::hasGet('query') ){
            $this->data = Load::model('twitter')->search(Input::get('query'));
        }
    }


    /////////////////// Necesita los token del usuario ///////////////////


    /**
     * Verificar los datos del usuario a través de los tokens
     */
    public function user() {
        $twitter = Load::model('twitter');
        $this->profile = $twitter->profile(
            $this->user_token,
            $this->secret_token
        );
    }

    /**
     * Con los token Tuiter en la cuenta de usuario
     */
    public function tweet(){
        if(Input::hasPost('tweet')){
            $twitter = Load::model('twitter');
            $tweet = $twitter->tweet(
                $this->user_token,
                $this->secret_token,
                Input::post('texto')
            );
            if ($tweet) Flash::success('Tweet enviado satisfactoriamente!!!');
        }
    }

}

?>