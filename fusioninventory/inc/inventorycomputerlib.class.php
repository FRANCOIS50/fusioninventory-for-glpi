<?php

/*
   ------------------------------------------------------------------------
   FusionInventory
   Copyright (C) 2010-2012 by the FusionInventory Development Team.

   http://www.fusioninventory.org/   http://forge.fusioninventory.org/
   ------------------------------------------------------------------------

   LICENSE

   This file is part of FusionInventory project.

   FusionInventory is free software: you can redistribute it and/or modify
   it under the terms of the GNU Affero General Public License as published by
   the Free Software Foundation, either version 3 of the License, or
   (at your option) any later version.

   FusionInventory is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
   GNU Affero General Public License for more details.

   You should have received a copy of the GNU Affero General Public License
   along with Behaviors. If not, see <http://www.gnu.org/licenses/>.

   ------------------------------------------------------------------------

   @package   FusionInventory
   @author    David Durieux
   @co-author
   @copyright Copyright (c) 2010-2012 FusionInventory team
   @license   AGPL License 3.0 or (at your option) any later version
              http://www.gnu.org/licenses/agpl-3.0-standalone.html
   @link      http://www.fusioninventory.org/
   @link      http://forge.fusioninventory.org/projects/fusioninventory-for-glpi/
   @since     2010

   ------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginFusioninventoryInventoryComputerLib extends CommonDBTM {

   var $table = "glpi_plugin_fusioninventory_inventorycomputerlibserialization";

   
   
   function updateComputer($a_computerinventory, $items_id) {
      global $DB;
      
      $computer                     = new Computer();
      $pfInventoryComputerComputer  = new PluginFusioninventoryInventoryComputerComputer();
      $item_DeviceProcessor         = new Item_DeviceProcessor();
      $deviceProcessor              = new DeviceProcessor();
      $item_DeviceMemory            = new Item_DeviceMemory();
      $deviceMemory                 = new DeviceMemory();
      $Software                     = new Software();
      $softwareVersion              = new SoftwareVersion();
      $computer_SoftwareVersion     = new Computer_SoftwareVersion();
      $computerVirtualmachine       = new ComputerVirtualMachine();
      $computerDisk                 = new ComputerDisk();
      $item_DeviceControl           = new Item_DeviceControl();
      $deviceControl                = new DeviceControl();
      $item_DeviceGraphicCard       = new Item_DeviceGraphicCard();
      $deviceGraphicCard            = new DeviceGraphicCard();
      $item_DeviceSoundCard         = new Item_DeviceSoundCard();
      $deviceSoundCard              = new DeviceSoundCard();
      $networkPort                  = new NetworkPort();
      $networkName                  = new NetworkName();
      $iPAddress                    = new IPAddress();
      $pfInventoryComputerAntivirus = new PluginFusioninventoryInventoryComputerAntivirus();
      $pfConfig                     = new PluginFusioninventoryConfig();
      
      $computer->getFromDB($items_id);
      
      $a_computerinventory = PluginFusioninventoryFormatconvert::computerReplaceids($a_computerinventory);
      
      $a_lockable = PluginFusioninventoryLock::getLockFields('glpi_computers', $items_id);
      
      // * Computer
         $db_computer = array();
         $a_field = array('name', 'operatingsystems_id', 'operatingsystemversions_id',  'os_licenseid',
                        'os_license_number', 'domains_id', 'uuid',  'comment', 'users_id', 'contact',
                        'manufacturers_id', 'computermodels_id', 'serial', 'computertypes_id');
         foreach ($a_field as $field) {
            $db_computer[$field] = $computer->fields[$field];
         }
         $a_ret = $this->checkLock($a_computerinventory['computer'], $db_computer, $a_lockable);
         $a_computerinventory['computer'] = $a_ret[0];
         $db_computer = $a_ret[1];
         $input = $this->diffArray($a_computerinventory['computer'], $db_computer);
         $input['id'] = $items_id;         
         if (isset($input['comment'])) {
            unset($input['comment']);
         }
         $computer->update($input);
         
         $input = $this->diffArray($a_computerinventory['computer'], $db_computer);
         if (isset($input['comment'])) {
            $inputcomment = array();
            $inputcomment['comment'] = $input['comment'];
            $inputcomment['id'] = $items_id; 
            $inputcomment['_no_history'] = true;
            $computer->update($input);
         }
      
      // * Computer fusion (ext)
         $db_computer = array();
         $query = "SELECT * FROM `glpi_plugin_fusioninventory_inventorycomputercomputers`
             WHERE `computers_id` = '$items_id'";
         $result = $DB->query($query);         
         while ($data = $DB->fetch_assoc($result)) {            
            foreach($data as $key=>$value) {
               $data[$key] = Toolbox::addslashes_deep($value);
            }
            $db_computer = $data;
         }
         if (count($db_computer) == '0') { // Add
            $a_computerinventory['fusioninventorycomputer']['computers_id'] = $items_id;
            $pfInventoryComputerComputer->add($a_computerinventory['fusioninventorycomputer']);
         } else { // Update
            $idtmp = $db_computer['id'];
            unset($db_computer['id']);
            unset($db_computer['computers_id']);
            $a_ret = $this->checkLock($a_computerinventory['fusioninventorycomputer'], $db_computer);
            $a_computerinventory['fusioninventorycomputer'] = $a_ret[0];
            $db_computer = $a_ret[1];
            $input = $this->diffArray($a_computerinventory['fusioninventorycomputer'], $db_computer);
            $input['id'] = $idtmp;
            $pfInventoryComputerComputer->update($input);
         }
         
         
      // * Processors
         
      // * Memories
         
      // * Graphiccard
         
      // * Sound
         
      // * Controllers
         
      // * Software
         if ($pfConfig->getValue($_SESSION["plugin_fusioninventory_moduleid"],
              "import_software", 'inventory') != 0) {
            $a_softwares = array();
            $query = "SELECT `glpi_computers_softwareversions`.`id` as sid,
                       `glpi_softwares`.`name`,
                       `glpi_softwareversions`.`name` AS version,
                       `glpi_softwareversions`.`entities_id`,
                       `glpi_softwares`.`manufacturers_id`
                FROM `glpi_computers_softwareversions`
                LEFT JOIN `glpi_softwareversions`
                     ON (`glpi_computers_softwareversions`.`softwareversions_id`
                           = `glpi_softwareversions`.`id`)
                LEFT JOIN `glpi_softwares`
                     ON (`glpi_softwareversions`.`softwares_id` = `glpi_softwares`.`id`)
                WHERE `glpi_computers_softwareversions`.`computers_id` = '$items_id'";
            $result = $DB->query($query);
            while ($db_software = $DB->fetch_assoc($result)) {
               $idtmp = $db_software['sid'];
               unset($db_software['sid']);
               if ($db_software['version'] == '') {
                  unset($db_software['version']);
               } 
               $db_software = Toolbox::addslashes_deep($db_software);
               $db_software = array_map('strtolower', $db_software);
               $a_softwares[$idtmp] = $db_software;
            }
            foreach ($a_computerinventory['software'] as $key => $arrays) {
               unset($arrays['manufacturer']);
               $arrayslower = array_map('strtolower', $arrays);
               foreach ($a_softwares as $keydb => $arraydb) {
                  if ($arrayslower == $arraydb) {
                     unset($a_computerinventory['software'][$key]);
                     unset($a_softwares[$keydb]);
                     break;
                  }
               }
            }
            if (count($a_computerinventory['software']) == 0
               AND count($a_softwares) == 0) {
               // Nothing to do
            } else {
               if (count($a_softwares) != 0) {
                  // Delete softwares in DB
                  foreach ($a_softwares as $idtmp => $data) {
                     $computer_SoftwareVersion->delete(array('id'=>$idtmp));
                  }
               }
               if (count($a_computerinventory['software']) != 0) {
                  foreach($a_computerinventory['software'] as $a_software) {
                     $softwares_id = $Software->addOrRestoreFromTrash($a_software['name'],
                                                                     $a_software['manufacturer'],
                                                                     $a_software['entities_id']);
                     $a_software['softwares_id'] = $softwares_id;
                     $a_software['name'] = $a_software['version'];
                     $softwareversions_id = $softwareVersion->add($a_software);
                     $a_software['computers_id'] = $items_id;
                     $a_software['softwareversions_id'] = $softwareversions_id;
                     $computer_SoftwareVersion->add($a_software);
                  }
               }
            }
         }
         
      // * Virtualmachines
         $db_computervirtualmachine = array();
         $query = "SELECT `id`, `name`, `uuid`, `virtualmachinesystems_id` FROM `glpi_computervirtualmachines`
             WHERE `computers_id` = '$items_id'";
         $result = $DB->query($query);         
         while ($data = $DB->fetch_assoc($result)) {
            $idtmp = $data['id'];
            unset($data['id']);            
            $data = Toolbox::addslashes_deep($data);
            $data = array_map('strtolower', $data);
            $db_computervirtualmachine[$idtmp] = $data;
         }
         $simplecomputervirtualmachine = array();
         foreach ($a_computerinventory['virtualmachine'] as $key=>$a_computervirtualmachine) {
            $a_field = array('name', 'uuid', 'virtualmachinesystems_id');
            foreach ($a_field as $field) {
               if (isset($a_computervirtualmachine[$field])) {
                  $simplecomputervirtualmachine[$key][$field] = $a_computervirtualmachine[$field];
               }
            }            
         }
         foreach ($simplecomputervirtualmachine as $key => $arrays) {
            $arrayslower = array_map('strtolower', $arrays);
            foreach ($db_computervirtualmachine as $keydb => $arraydb) {
               if ($arrayslower == $arraydb) {
                  $input = array();
                  $input['id'] = $keydb;
                  if (isset($a_computerinventory['virtualmachine'][$key]['vcpu'])) {
                     $input['vcpu'] = $a_computerinventory['virtualmachine'][$key]['vcpu'];
                  }
                  if (isset($a_computerinventory['virtualmachine'][$key]['ram'])) {
                     $input['ram'] = $a_computerinventory['virtualmachine'][$key]['ram'];
                  }
                  if (isset($a_computerinventory['virtualmachine'][$key]['virtualmachinetypes_id'])) {
                     $input['virtualmachinetypes_id'] = $a_computerinventory['virtualmachine'][$key]['virtualmachinetypes_id'];
                  }
                  if (isset($a_computerinventory['virtualmachine'][$key]['virtualmachinestates_id'])) {
                     $input['virtualmachinestates_id'] = $a_computerinventory['virtualmachine'][$key]['virtualmachinestates_id'];
                  }
                  $computerVirtualmachine->update($input);
                  unset($simplecomputervirtualmachine[$key]);
                  unset($a_computerinventory['virtualmachine'][$key]);
                  unset($db_computervirtualmachine[$keydb]);
                  break;
               }
            }
         }
         if (count($a_computerinventory['virtualmachine']) == 0
            AND count($db_computervirtualmachine) == 0) {
            // Nothing to do
         } else {
            if (count($db_computervirtualmachine) != 0) {
               // Delete softwares in DB
               foreach ($db_computervirtualmachine as $idtmp => $data) {
                  $computerVirtualmachine->delete(array('id'=>$idtmp));
               }
            }
            if (count($a_computerinventory['virtualmachine']) != 0) {
               foreach($a_computerinventory['virtualmachine'] as $a_virtualmachine) {
                  $a_virtualmachine['computers_id'] = $items_id;
                  $computerVirtualmachine->add($a_virtualmachine);
               }
            }
         }
         
      // * ComputerDisk
         $db_computerdisk = array();
         $query = "SELECT `id`, `name`, `device`, `mountpoint` FROM `glpi_computerdisks`
             WHERE `computers_id` = '$items_id'";
         $result = $DB->query($query);         
         while ($data = $DB->fetch_assoc($result)) {
            $idtmp = $data['id'];
            unset($data['id']);            
            $data = Toolbox::addslashes_deep($data);
            $data = array_map('strtolower', $data);
            $db_computerdisk[$idtmp] = $data;
         }
         $simplecomputerdisk = array();
         foreach ($a_computerinventory['computerdisk'] as $key=>$a_computerdisk) {
            $a_field = array('name', 'device', 'mountpoint');
            foreach ($a_field as $field) {
               if (isset($a_computerdisk[$field])) {
                  $simplecomputerdisk[$key][$field] = $a_computerdisk[$field];
               }
            }            
         }
         foreach ($simplecomputerdisk as $key => $arrays) {
            $arrayslower = array_map('strtolower', $arrays);
            foreach ($db_computerdisk as $keydb => $arraydb) {
               if ($arrayslower == $arraydb) {
                  $input = array();
                  $input['id'] = $keydb;
                  if (isset($a_computerinventory['computerdisk'][$key]['filesystems_id'])) {
                     $input['filesystems_id'] = $a_computerinventory['computerdisk'][$key]['filesystems_id'];
                  }
                  $input['totalsize'] = $a_computerinventory['computerdisk'][$key]['totalsize'];                  
                  $input['freesize'] = $a_computerinventory['computerdisk'][$key]['freesize'];
                  $computerDisk->update($input);
                  unset($simplecomputerdisk[$key]);
                  unset($a_computerinventory['computerdisk'][$key]);
                  unset($db_computerdisk[$keydb]);
                  break;
               }
            }
         }
         if (count($a_computerinventory['computerdisk']) == 0
            AND count($db_computerdisk) == 0) {
            // Nothing to do
         } else {
            if (count($db_computerdisk) != 0) {
               // Delete softwares in DB
               foreach ($db_computerdisk as $idtmp => $data) {
                  $computerDisk->delete(array('id'=>$idtmp));
               }
            }
            if (count($a_computerinventory['computerdisk']) != 0) {
               foreach($a_computerinventory['computerdisk'] as $a_computerdisk) {
                  $a_computerdisk['computers_id'] = $items_id;
                  $computerDisk->add($a_computerdisk);
               }
            }
         }
         
     
      // * Networkports
         $db_networkport = array();
         $query = "SELECT `id`, `name`, `mac`, `instantiation_type` FROM `glpi_networkports`
             WHERE `items_id` = '$items_id'
               AND `itemtype`='Computer'";
         $result = $DB->query($query);         
         while ($data = $DB->fetch_assoc($result)) {
            $idtmp = $data['id'];
            unset($data['id']);
            if (is_null($data['mac'])) {
               unset($data['mac']);
            }
            $data = Toolbox::addslashes_deep($data);
            $data = array_map('strtolower', $data);
            $db_networkport[$idtmp] = $data;
         }
         $simplenetworkport = array();
         foreach ($a_computerinventory['networkport'] as $key=>$a_networkport) {
            $a_field = array('name', 'mac', 'instantiation_type');
            foreach ($a_field as $field) {
               if (isset($a_networkport[$field])) {
                  $simplenetworkport[$key][$field] = $a_networkport[$field];
               }
            }            
         }
         foreach ($simplenetworkport as $key => $arrays) {
            $arrayslower = array_map('strtolower', $arrays);
            foreach ($db_networkport as $keydb => $arraydb) {
               if ($arrayslower == $arraydb) {
                  // Get networkname
                  $a_networknames_find = current($networkName->find("`items_id`='".$keydb."'
                                                             AND `itemtype`='NetworkPort'", "", 1));
                  
                  // Same networkport, verify ipaddresses
                  $db_addresses = array();
                  $query = "SELECT `id`, `name` FROM `glpi_ipaddresses`
                      WHERE `items_id` = '".$a_networknames_find['id']."'
                        AND `itemtype`='NetworkName'";
                  $result = $DB->query($query);         
                  while ($data = $DB->fetch_assoc($result)) {
                     $db_addresses[$data['id']] = $data['name'];
                  }
                  $a_computerinventory_ipaddress = $a_computerinventory['networkport'][$key]['ipaddress'];
                  foreach ($a_computerinventory_ipaddress as $key2 => $arrays2) {
                     foreach ($db_addresses as $keydb2 => $arraydb2) {
                        if ($arrays2 == $arraydb2) {
                           unset($a_computerinventory_ipaddress[$key2]);
                           unset($db_addresses[$keydb2]);
                           break;
                        }
                     }
                  }
                  if (count($a_computerinventory_ipaddress) == 0
                     AND count($db_addresses) == 0) {
                     // Nothing to do
                  } else {
                     if (count($db_addresses) != 0) {
                        // Delete softwares in DB                     
                        foreach ($db_addresses as $idtmp => $name) {
                           $iPAddress->delete(array('id'=>$idtmp));
                        }
                     }
                     if (count($a_computerinventory_ipaddress) != 0) {
                        foreach ($a_computerinventory_ipaddress as $ip) {
                           $input = array();
                           $input['items_id'] = $a_networknames_find['id'];
                           $input['itemtype'] = 'NetworkName';
                           $input['name'] = $ip;
                           $iPAddress->add($input);
                        }
                     }
                  }
                  
                  unset($db_networkport[$keydb]);
                  unset($simplenetworkport[$key]);
                  unset($a_computerinventory['networkport'][$key]);
                  break;
               }
            }
         }
         
         if (count($a_computerinventory['networkport']) == 0
            AND count($db_networkport) == 0) {
            // Nothing to do
         } else {
            if (count($db_networkport) != 0) {
               // Delete softwares in DB
               foreach ($db_networkport as $idtmp => $data) {
                  $networkPort->delete(array('id'=>$idtmp));
               }
            }
            if (count($a_computerinventory['networkport']) != 0) {
               foreach ($a_computerinventory['networkport'] as $a_networkport) {
                  $a_networkport['entities_id'] = $_SESSION["plugin_fusinvinventory_entity"];
                  $a_networkport['items_id'] = $items_id;
                  $a_networkport['itemtype'] = "Computer";
                  $a_networkport['items_id'] = $networkPort->add($a_networkport);
                  $a_networkport['is_recursive'] = 0;
                  $a_networkport['itemtype'] = 'NetworkPort';
                  $a_networknames_id = $networkName->add($a_networkport);
                  foreach ($a_networkport['ipaddress'] as $ip) {
                     $input = array();
                     $input['items_id'] = $a_networknames_id;
                     $input['itemtype'] = 'NetworkName';
                     $input['name'] = $ip;
                     $iPAddress->add($input);
                  }
               }
            }
         }   
         
         
      // * Antivirus
         $db_antivirus = array();
         $query = "SELECT `id`, `name`, `version` FROM `glpi_plugin_fusioninventory_inventorycomputerantiviruses`
             WHERE `computers_id` = '$items_id'";
         $result = $DB->query($query);         
         while ($data = $DB->fetch_assoc($result)) {
            $idtmp = $data['id'];
            unset($data['id']);            
            $data = Toolbox::addslashes_deep($data);
            $data = array_map('strtolower', $data);
            $db_antivirus[$idtmp] = $data;
         }
         $simpleantivirus = array();
         foreach ($a_computerinventory['antivirus'] as $key=>$a_antivirus) {
            $a_field = array('name', 'version');
            foreach ($a_field as $field) {
               if (isset($a_antivirus[$field])) {
                  $simpleantivirus[$key][$field] = $a_antivirus[$field];
               }
            }            
         }
         foreach ($simpleantivirus as $key => $arrays) {
            $arrayslower = array_map('strtolower', $arrays);
            foreach ($db_antivirus as $keydb => $arraydb) {
               if ($arrayslower == $arraydb) {
                  $input = array();
                  $input = $a_computerinventory['virtualmachine'][$key];
                  $input['id'] = $keydb;
                  $pfInventoryComputerAntivirus->update($input);
                  unset($simpleantivirus[$key]);
                  unset($a_computerinventory['antivirus'][$key]);
                  unset($db_antivirus[$keydb]);
                  break;
               }
            }
         }
         if (count($a_computerinventory['antivirus']) == 0
            AND count($db_antivirus) == 0) {
            // Nothing to do
         } else {
            if (count($db_antivirus) != 0) {
               foreach ($db_antivirus as $idtmp => $data) {
                  $pfInventoryComputerAntivirus->delete(array('id'=>$idtmp));
               }
            }
            if (count($a_computerinventory['antivirus']) != 0) {
               foreach($a_computerinventory['antivirus'] as $a_antivirus) {
                  $a_antivirus['computers_id'] = $items_id;
                  $pfInventoryComputerAntivirus->add($a_antivirus);
               }
            }
         }
      
   }
   
   
   
   static function checkLock($datainventory, $db_computer, $a_lockable=array()) {
      foreach($a_lockable as $field) {
         if (isset($datainventory[$field])) {
            unset($datainventory[$field]);
         }
         if (isset($db_computer[$field])) {
            unset($db_computer[$field]);
         }
      }
      return array($datainventory, $db_computer);
   }
   
   
   
   function diffArray($array1, $array2) {

      $a_return = array();
      foreach ($array1 as $key=>$value) {
         $key2 = false;
         $key2 = array_search($value, $array2, true);
         if ($key2) {
            unset($array2[$key2]);
         } else {
            $a_return[$key] = $value;
         }
      }
      return $a_return;
   }
   
   
   
   
// ************************************************************************************ //
// ******************************** old *********************************************** //
// ************************************************************************************ //

   /**
   * Sarting create or update computer and get Entity
   *
   * @param $simpleXMLObj simplexml xmlobject of the computer
   * @param $items_id interger id of the computer
   * @param $new bool if 1 create a new computer
   *
   * @return nothing
   *
   **/
   function startAction($arrayinventory, $items_id, $new=0) {
      global $DB;

      // Convert *s_id (name) into real *s_id (id) of $arrayinventory
      $arrayinventory = PluginFusioninventoryFormatconvert::computerReplaceids($arrayinventory);
      Toolbox::logInFile("DATA", print_r($arrayinventory, true));
      
      exit;

      if ($new == "0") {
         // Transfer if entity is different
         $Computer = new Computer();
         $Computer->getFromDB($items_id);
         $input = array();
         $input['id'] = $Computer->fields['id'];
         $input['autoupdatesystems_id'] = Dropdown::importExternal('AutoUpdateSystem',
                                                                   'FusionInventory',
                                                                   $_SESSION["plugin_fusinvinventory_entity"]);
         $_SESSION['glpiactiveentities'] = array($Computer->fields['entities_id']);
         $_SESSION['glpiactiveentities_string'] = $Computer->fields['entities_id'];
         $_SESSION['glpiactive_entity'] = $Computer->fields['entities_id'];
         $input['is_ocs_import'] = 0;
         $Computer->update($input);
         if ($_SESSION["plugin_fusinvinventory_entity"] == NOT_AVAILABLE) {
            $_SESSION["plugin_fusinvinventory_entity"] = $Computer->fields['entities_id'];
         }
         $pfConfig = new PluginFusioninventoryConfig();
         if ($Computer->getEntityID() != $_SESSION["plugin_fusinvinventory_entity"]) {
            $Transfer = new Transfer();
            // get value in Config ($config['transfers_id_auto'])
            $Transfer->getFromDB(
                $pfConfig->getValue($_SESSION["plugin_fusioninventory_moduleid"],
                                                       'transfers_id_auto', 'inventory'));

            $item_to_transfer = array("Computer" => array($items_id=>$items_id));

            $Transfer->moveItems($item_to_transfer, $_SESSION["plugin_fusinvinventory_entity"],
                                 $Transfer->fields);
         }

         // Get internal ID with $items_id
         $a_serialized = array();
         $query = "SELECT internal_id FROM `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
                   WHERE `computers_id`='".$items_id."'
                   LIMIT 1";
         $result = $DB->query($query);
         if ($result) {
            if ($DB->numrows($result) == '1') {
               $a_serialized = $DB->fetch_assoc($result);
            }
         }
         $internalId = uniqid("", true);
         if (isset($a_serialized['internal_id'])) {
            $internalId = $a_serialized['internal_id'];
         } else {
            // load GLPI data in the XML
            $pfInventoryComputerInventory = new PluginFusioninventoryInventoryComputerInventory();
            $pfInventoryComputerInventory->createMachineInLib($items_id, $internalId);
         }

         // Link computer to agent FusionInventory
         $pfAgent = new PluginFusioninventoryAgent();
         $pfAgent->setAgentWithComputerid($items_id, $arrayinventory['DEVICEID']);

         // Transfer agent entity
         $pfAgent = new PluginFusioninventoryAgent();
         $agent_id = $pfAgent->getAgentWithComputerid($items_id);
         if ($agent_id) {
            $pfAgent->getFromDB($agent_id);
            if ($pfAgent->getEntityID() != $_SESSION["plugin_fusinvinventory_entity"]) {
               $input = array();
               $input['id'] = $pfAgent->fields['id'];
               $input['entities_id'] = $_SESSION["plugin_fusinvinventory_entity"];
               $pfAgent->update($input);
            }
         }

         //Sections update
         $xmlSections = $this->_getXMLSections($arrayinventory);
         $this->updateLibMachine($xmlSections, $internalId);

//         $pfInventoryComputerLibhook = new PluginFusioninventoryInventoryComputerLibhook();
// TODO WRITE         $pfInventoryComputerLibhook->writeXMLFusion($items_id, $xml->asXML());
      } else {
         // New Computer
         if ($_SESSION["plugin_fusinvinventory_entity"] == NOT_AVAILABLE) {
            $_SESSION["plugin_fusinvinventory_entity"] = 0;
         }
         $_SESSION['glpiactiveentities'] = array($_SESSION["plugin_fusinvinventory_entity"]);
         $_SESSION['glpiactiveentities_string'] = $_SESSION["plugin_fusinvinventory_entity"];
         $_SESSION['glpiactive_entity'] = $_SESSION["plugin_fusinvinventory_entity"];
         
         
         
         
         //We launch CreateMachine() hook and provide an InternalId
         $xmlSections = $this->_getXMLSections($arrayinventory);
         $internalId = uniqid("", true);

         //try {
            $pfInventoryComputerLibhook = new PluginFusioninventoryInventoryComputerLibhook();
            $pfInventoryComputerLibhook->createMachine($items_id);

            // Link computer to agent FusionInventory
            $pfAgent = new PluginFusioninventoryAgent();
            $pfAgent->setAgentWithComputerid($items_id, $arrayinventory['DEVICEID']);

            // Transfer agent entity
            $pfAgent = new PluginFusioninventoryAgent();
            $agent_id = $pfAgent->getAgentWithComputerid($items_id);
            if ($agent_id) {
               $pfAgent->getFromDB($agent_id);
               if ($pfAgent->getEntityID()
                     != $_SESSION["plugin_fusinvinventory_entity"]) {
                  $pfAgent->fields['entities_id']
                     = $_SESSION["plugin_fusinvinventory_entity"];
                  $pfAgent->update($pfAgent->fields);
               }
            }

            $this->addLibMachine($internalId, $items_id);

//            $pfInventoryComputerLibhook->writeXMLFusion($items_id, $xml->asXML());

            $this->updateLibMachine($xmlSections, $internalId);

      }
   }



   /**
   * Create computer into lib
   *
   * @param $internalId value uniq id for internal lib
   * @param $externalId integer id of the GLPI computer
   *
   * @return nothing
   *
   */
   public function addLibMachine($internalId, $externalId) {
      global $DB;

      $queryInsert = "INSERT INTO `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
                      ( `internal_id`, `computers_id`)
                      VALUES ('" . $internalId . "', '".$externalId."')";
      $DB->query($queryInsert);
  }



   /**
   * Update/insert sections into lib DB
   * Must be inserted into many yimes because default configuration of MySQL
   * can only insert 1 Mo of datas and sometimes we have more than this
   *
   * @param $internalId value uniq id of internal lib
   * @param $serializedSections value XML sections serialized
   *
   * @return nothing
   *
   */
   function _serializeIntoDB($internalId, $serializedSections) {
      global $DB;

#      $serializedSections = str_replace("\\", "\\\\", $serializedSections);
      $a_serializedSections = str_split($serializedSections, 800000);

      $queryUpdate = "UPDATE `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
      SET `serialized_sections1` = '".$a_serializedSections[0]."',
         `last_fusioninventory_update`='".date("Y-m-d H:i:s")."'
      WHERE `internal_id` = '" . $internalId . "'";

      $resultUpdate = $DB->query($queryUpdate);

      if (isset($a_serializedSections[1])) {
         $queryUpdate = "UPDATE `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
         SET `serialized_sections2` = '" . $a_serializedSections[1] ."'
         WHERE `internal_id` = '" . $internalId . "'";
      } else {
         $queryUpdate = "UPDATE `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
         SET `serialized_sections2` = ''
         WHERE `internal_id` = '" . $internalId . "'";
      }
      $resultUpdate = $DB->query($queryUpdate);

      if (isset($a_serializedSections[2])) {
        $queryUpdate = "UPDATE `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
         SET `serialized_sections3` = '" . $a_serializedSections[2] ."'
         WHERE `internal_id` = '" . $internalId . "'";
      } else {
         $queryUpdate = "UPDATE `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
         SET `serialized_sections3` = ''
         WHERE `internal_id` = '" . $internalId . "'";
      }
      $resultUpdate = $DB->query($queryUpdate);
   }



   /**
   * get all sections with its serialized datas,and sectionId from database
   *
   * @param $internalId value uniq id of internal lib
   *
   * @return array $infoSections (serialized datas and sectionId)
   */
   function _getInfoSections($internalId) {
      global $DB;

      $infoSections = array();
      $infoSections["externalId"] = '';
      $infoSections["sections"] = array();
      $infoSections["sectionsToModify"] = array();

      /* Variables for the recovery and changes in the serialized sections */
      $serializedSections = "";
      $arraySerializedSections = array();
      $arraySerializedSectionsTemp = array();

      $querySelect = "SELECT `computers_id`, `serialized_sections1`, `serialized_sections2`, `serialized_sections3`
            FROM `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
         WHERE `internal_id` = '$internalId'";
      $resultSelect = $DB->query($querySelect);
      $data = $DB->fetch_assoc($resultSelect);
      $infoSections["externalId"] = $data['computers_id'];
      $serializedSections = $data['serialized_sections1'].$data['serialized_sections2'].$data['serialized_sections3'];
      $arraySerializedSections = explode("\n", $serializedSections); // Recovering a table with one line per entry
      $previous_infosection = array();
      foreach ($arraySerializedSections as $valeur) {

         $arraySerializedSectionsTemp = explode("<<=>>", $valeur); // For each line, we create a table with data separated
         if (isset($arraySerializedSectionsTemp[0]) AND isset($arraySerializedSectionsTemp[1])) {
            if ($arraySerializedSectionsTemp[0] != "" && $arraySerializedSectionsTemp[1] != "") { // that is added to infosections
               if (!preg_match("/}$/", $arraySerializedSectionsTemp[1])) {
                  $infoSections["sections"][$arraySerializedSectionsTemp[0]] = $arraySerializedSectionsTemp[1];
               }
            }
            $previous_infosection = $arraySerializedSectionsTemp[0];
         } else if ($valeur != '') {
            $infoSections["sections"][$previous_infosection] .= "\n".$valeur;
         }
      }
      $infoSections['sections'] = $this->convertData($infoSections['sections']);
      return $infoSections;
   }



   static function convertData($infoSections) {
      foreach ($infoSections as $key=>$value) {
         $matches = array();
         $matches1 = array();
         preg_match("/(a:\d+:)\{(.*)\}(\w+)/sm", $value, $matches1);

         preg_match_all('/s:\d+:"(.*?)";/sm', $matches1[2], $matches);
         $constuctArray = array();
         $i = 0;
         $size = count($matches[1]);
         for ($i = 0; $i < $size; $i = $i+2) {
            $constuctArray[$matches[1][$i]] = Toolbox::clean_cross_side_scripting_deep(Toolbox::addslashes_deep($matches[1][($i+1)]));
         }
         $infoSections[$key] = serialize($constuctArray).$matches1[3];
      }
      return $infoSections;
   }



   /**
   * Unserialize sections
   *
   * @param $sObject value datas serialized
   *
   * @return array each sections into an array
   */
   function __unserialize($sObject) {
      $__ret =preg_replace('!s:(\d+):"(.*?)";!e', "'s:'.strlen('$2').':\"$2\";'", $sObject );
      return unserialize($__ret);
   }



   /**
   * When purge computer, we delete entry in lib DB
   *
   * @param $computers_id integer GLPI id of the computer
   *
   * @return nothing
   */
   function removeExternalid($computers_id) {
      global $DB;

      $query_delete = "DELETE FROM `glpi_plugin_fusioninventory_inventorycomputerlibserialization`
         WHERE `computers_id`='".$computers_id."' ";
      $DB->query($query_delete);
   }



   /**
   * Add existant GLPI computer into lib before update it
   *
   * @param $items_id integer id of GLPI Computer
   * @param $internal_id value uniq id of the computer in lib
   * @param $arrayObj array of the Computer
   * @param $a_sectionsinfos
   *
   * @return nothing
   */
   function addLibMachineFromGLPI($items_id, $internal_id, $arrayObj, $a_sectionsinfos) {
      $this->addLibMachine($internal_id, $items_id);

      $xmlSections = $this->_getXMLSections($arrayObj);

      $serializedSectionsFromXML = array();

      foreach($xmlSections as $xmlSection) {
         array_push($serializedSectionsFromXML, $xmlSection["sectionDatawName"]);
      }

      $serializedSections = "";
      foreach($serializedSectionsFromXML as $key => $serializedSection) {
         if (!strstr($key, "ENVS/")
               AND !strstr($key, "PROCESSES/")) {

            $serializedSections .= array_shift($a_sectionsinfos)."<<=>>".$serializedSection."
";
         }
      }
      $this->_serializeIntoDB($internal_id, $serializedSections);
   }




}

?>