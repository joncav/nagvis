<?php
/*******************************************************************************
 *
 * CoreModAutoMap.php - Core Automap module to handle ajax requests
 *
 * Copyright (c) 2004-2011 NagVis Project (Contact: info@nagvis.org)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 ******************************************************************************/

/**
 * @author Lars Michelsen <lars@vertical-visions.de>
 */
class CoreModAutoMap extends CoreModule {
	private $name = null;
	private $aOpts = null;
	private $opts = null;
	
	public function __construct(GlobalCore $CORE) {
		$this->sName = 'AutoMap';
		$this->CORE = $CORE;
		
		$this->aOpts = Array('show' => MATCH_MAP_NAME,
		               'backend' => MATCH_STRING_NO_SPACE_EMPTY,
		               'root' => MATCH_STRING_NO_SPACE_EMPTY,
		               'maxLayers' => MATCH_INTEGER_PRESIGN_EMPTY,
		               'childLayers' => MATCH_INTEGER_PRESIGN_EMPTY,
		               'parentLayers' => MATCH_INTEGER_PRESIGN_EMPTY,
		               'renderMode' => MATCH_AUTOMAP_RENDER_MODE,
		               'width' => MATCH_INTEGER_EMPTY,
		               'height' => MATCH_INTEGER_EMPTY,
		               'ignoreHosts' => MATCH_STRING_NO_SPACE_EMPTY,
		               'filterGroup' => MATCH_STRING_EMPTY,
		               'filterByState' => MATCH_STRING_NO_SPACE_EMPTY);
		
		$aVals = $this->getCustomOptions($this->aOpts);
		$this->name = $aVals['show'];
		unset($aVals['show']);
		$this->opts = $aVals;
		
		// Save the automap name to use
		$this->opts['automap'] = $this->name;
		// Save the preview mode (Enables/Disables printing of errors)
		$this->opts['preview'] = 0;
		
		// Register valid actions
		$this->aActions = Array(
			'parseAutomap'         => 'view',
			'getAutomapProperties' => 'view',
			'getAutomapObjects'    => 'view',
			'getObjectStates'      => 'view',
			'automapToMap'         => 'edit',
			'modifyParams'         => 'edit',
			'parseMapCfg'          => 'edit',
		);
		
		// Register valid objects
		$this->aObjects = $this->CORE->getAvailableAutomaps(null, SET_KEYS);
		
		// Set the requested object for later authorisation
		$this->setObject($this->name);
	}
	
	public function handleAction() {
		$sReturn = '';
		
		if($this->offersAction($this->sAction)) {
			switch($this->sAction) {
				case 'parseAutomap':
				case 'parseMapCfg':
					$sReturn = $this->parseAutomap();
				break;
				case 'getAutomapProperties':
					$sReturn = $this->getAutomapProperties();
				break;
				case 'getAutomapObjects':
					$sReturn = $this->getAutomapObjects();
				break;
				case 'getObjectStates':
					$sReturn = $this->getObjectStates();
				break;
				case 'automapToMap':
					$VIEW = new NagVisViewAutomapToMap($this->CORE);
          $sReturn = json_encode(Array('code' => $VIEW->parse()));
				break;
				case 'modifyParams':
					$VIEW = new NagVisViewAutomapModifyParams($this->CORE, $this->opts);
					$sReturn = json_encode(Array('code' => $VIEW->parse()));
				break;
			}
		}
		
		return $sReturn;
	}
	
	private function parseAutomap() {
		// Initialize backends
		$BACKEND = new CoreBackendMgmt($this->CORE);
		
		$MAPCFG = new NagVisAutomapCfg($this->CORE, $this->name);
		$MAPCFG->readMapConfig();
		
		$MAP = new NagVisAutoMap($this->CORE, $MAPCFG, $BACKEND, $this->opts, IS_VIEW);

		if($this->sAction == 'parseAutomap') {
			$MAP->renderMap();
			return json_encode(true);
		} else {
			$FHANDLER = new CoreRequestHandler($_POST);
			if($FHANDLER->match('target', MATCH_MAP_NAME)) {
				$target = $FHANDLER->get('target');
			
				if($MAP->toClassicMap($target)) {
					new GlobalMessage('NOTE', 
														$this->CORE->getLang()->getText('The map has been created.'),
														null,
														null,
														1,
														$this->CORE->getMainCfg()->getValue('paths','htmlbase').'/frontend/nagvis-js/index.php?mod=Map&show='.$target);
				}  else {
					new GlobalMessage('ERROR', $this->CORE->getLang()->getText('Unable to create map configuration file.'));
				}
			} else {
				new GlobalMessage('ERROR', $this->CORE->getLang()->getText('Invalid target option given.'));
			}
		}
	}
	
	private function getAutomapProperties() {
		$MAPCFG = new NagVisAutomapCfg($this->CORE, $this->name);
		$MAPCFG->readMapConfig(ONLY_GLOBAL);
		
		$arr = Array();
		$arr['map_name']                 = $MAPCFG->getName();
		$arr['alias']                    = $MAPCFG->getValue(0, 'alias');
		$arr['map_image']                = $MAPCFG->getValue(0, 'map_image');
		$arr['background_usemap']        = '#automap';
		$arr['background_color']         = $MAPCFG->getValue(0, 'background_color');
		$arr['favicon_image']            = $this->CORE->getMainCfg()->getValue('paths', 'htmlimages').'internal/favicon.png';
		$arr['page_title']               = $MAPCFG->getValue(0, 'alias').' ([SUMMARY_STATE]) :: '.$this->CORE->getMainCfg()->getValue('internal', 'title');
		$arr['event_background']         = $MAPCFG->getValue(0, 'event_background');
		$arr['event_highlight']          = $MAPCFG->getValue(0, 'event_highlight');
		$arr['event_highlight_interval'] = $MAPCFG->getValue(0, 'event_highlight_interval');
		$arr['event_highlight_duration'] = $MAPCFG->getValue(0, 'event_highlight_duration');
		$arr['event_log']                = $MAPCFG->getValue(0, 'event_log');
		$arr['event_log_level']          = $MAPCFG->getValue(0, 'event_log_level');
		$arr['event_log_events']         = $MAPCFG->getValue(0, 'event_log_events');
		$arr['event_log_height']         = $MAPCFG->getValue(0, 'event_log_height');
		$arr['event_log_hidden']         = $MAPCFG->getValue(0, 'event_log_hidden');
		$arr['event_scroll']             = $MAPCFG->getValue(0, 'event_scroll');
		$arr['event_sound']              = $MAPCFG->getValue(0, 'event_sound');
		
		return json_encode($arr);
	}
	
	private function getAutomapObjects() {
		// Initialize backends
		$BACKEND = new CoreBackendMgmt($this->CORE);
		
		$MAPCFG = new NagVisAutomapCfg($this->CORE, $this->name);
		$MAPCFG->readMapConfig();
		
		$MAP = new NagVisAutoMap($this->CORE, $MAPCFG, $BACKEND, $this->opts, IS_VIEW);
		
		// Read position from graphviz and set it on the objects
		$MAP->setMapObjectPositions();
		$MAP->createObjectConnectors();

		return $MAP->parseObjectsJson();
	}

	private function getObjectStates() {
		$arrReturn = Array();
		
		$aOpts = Array('ty' => MATCH_GET_OBJECT_TYPE,
		               'i'  => MATCH_STRING_NO_SPACE_EMPTY);
		$aVals = $this->getCustomOptions($aOpts);
		
		// Initialize backends
		$BACKEND = new CoreBackendMgmt($this->CORE);
		
		// Read the map configuration file
		$MAPCFG = new NagVisAutomapCfg($this->CORE, $this->name);
		$MAPCFG->readMapConfig();

		// i might not be set when all map objects should be fetched or when only
		// the summary of the map is called
		if($aVals['i'] != '') {
			$MAPCFG->filterMapObjects($aVals['i']);

			// Filter by explicit list of host object ids
			$this->opts['filterByIds'] = $aVals['i'];
		}

		$MAP = new NagVisAutoMap($this->CORE, $MAPCFG, $BACKEND, $this->opts, IS_VIEW);
		$MAPOBJ = $MAP->MAPOBJ;
		return $MAP->parseObjectsJson($aVals['ty']);
	}
}
?>
