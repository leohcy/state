<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Statistics extends Controller_Common {

	public function action_scheduled() {
		$tasks = array();
		$servers = Kohana::$config->load('httpclient.servers');
		foreach($servers as $server) {
			$server = $server['server'];
			$response = Request::factory("http://$server/statistics.jsp")->execute();
			$response = json_decode($response->body(), TRUE);
			if(!$response['success'])
				continue;
			foreach($response['tasks'] as $task) {
				if($task['uri'] != 'user/update/value')
					continue;
				if($task['path'] != 'profile.olstat.status')
					continue;
				$tasks[$task['id']] = array(
					'server' => $server,
					'timeout' => $task['nextFireTime']
				);
			}
		}
		$this->model('success', TRUE);
		$this->model('count', count($tasks));
		$this->model('tasks', $tasks);
	}

}
