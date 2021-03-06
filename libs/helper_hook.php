<?php

trait SDB_HelperHook
{
  private $hook = 'SystemDashboard';
  
  private function RegisterHook($WebHook)
  {
      $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
      if (count($ids) > 0) {
          $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
          $found = false;
          foreach ($hooks as $index => $hook) {
              if ($hook['Hook'] == $WebHook) {
                  if ($hook['TargetID'] == $this->InstanceID) {
                      return;
                  }
                  $hooks[$index]['TargetID'] = $this->InstanceID;
                  $found = true;
              }
          }
          if (!$found) {
              $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
          }
          IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
          IPS_ApplyChanges($ids[0]);
      }
  }  
}