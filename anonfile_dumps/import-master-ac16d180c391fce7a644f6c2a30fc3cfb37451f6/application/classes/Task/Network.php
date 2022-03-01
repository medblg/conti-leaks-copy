<?php defined('SYSPATH') or die('No direct script access.');

class Task_Network extends Minion_Task {

    protected $_options = [
        'type' => NULL,
    ];

	protected function _execute(array $params){
        $limit = 1000;
        $id_low = DB::expr('id_low AS cid0');
        $id_high = DB::expr('id_high AS cid1');
        $records = DB::select($id_low, $id_high, 'group', 'created_at', 'process_info', 'sys_info')
            ->from('data90')
            ->limit($limit)
            ->execute()
            ->as_array();

        $client = Task_Helper::getStorageClient();

        $delete = [];
        $db_delete = DB::delete('data90');

        foreach($records as $r){
            $client->addTaskHighBackground("Insert:Network", json_encode($r));

            $r['id_low'] = $r['cid0'];
            unset($r['cid0']);
            $r['id_high'] = $r['cid1'];
            unset($r['cid1']);

            $delete[] = Arr::extract($r, ['id_low', 'id_high', 'created_at']);

            $db = Database::instance();
            $autoremove_ttl = Kohana::$config->load('init.network_archive.autoremove_ttl');
            if ($autoremove_ttl) {
                try {
                    DB::insert('network_archive', array_keys($r))
                        ->values($r)
                        ->execute($db);
                } catch(Database_Exception $e) {
                    Kohana::$log->add(Log::ERROR, Kohana_Exception::text($e), null, ['exception' => $e]);
                }
            }
        }
        $client->runTasks();

        if($delete){
            $db_delete->where(DB::expr('(id_low, id_high, created_at)'), 'IN', $delete)
                ->execute();
        }
    }
}