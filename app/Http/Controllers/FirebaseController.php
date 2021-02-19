<?php

namespace App\Http\Controllers;
use Kreait\Firebase\Factory;
use Illuminate\Http\Request;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging;
class FirebaseController extends APIController
{
	public $messaging;
  function __construct(){
  	$path = 'http\controllers\payhiram-firebase-adminsdk-nn06x-910d50fc3a.json';
  	if(env('OS') == 'linux'){
  		$path = 'Http/controllers/payhiram-firebase-adminsdk-nn06x-910d50fc3a.json';
  	}
  	$factory = (new Factory)->withServiceAccount(app_path($path));
  	$this->messaging = $factory->createMessaging();
  }


  public function send(Request $request){
  	$data = $request->all();
  	$message = CloudMessage::fromArray([
	    'topic' => $data['topic'],
	    'notification' => $data['notification'], // optional
	    'data' => $data['data'], // optional
		]);
		$this->messaging->send($message);
  }

  public function sendLocal($data){
  	$message = CloudMessage::fromArray([
	    'topic' => $data['topic'],
	    'notification' => $data['notification'], // optional
	    'data' => $data['data'], // optional
		]);
		$this->messaging->send($message);
  }

}
