<?php
/*
    ***** BEGIN LICENSE BLOCK *****
    
    This file is part of the Zotero Data Server.
    
    Copyright © 2013 Center for History and New Media
                     George Mason University, Fairfax, Virginia, USA
                     http://zotero.org
    
    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.
    
    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
    
    ***** END LICENSE BLOCK *****
*/

require('ApiController.php');

class TagsController extends ApiController {
	public function tags() {
		$this->allowMethods(array('GET', 'DELETE'));
		
		if (!$this->permissions->canAccess($this->objectLibraryID)) {
			$this->e403();
		}
		
		$tagIDs = array();
		$results = array();
		$totalResults = 0;
		$name = $this->objectName;
		$fixedValues = array();
		
		// Set of tags matching name
		if ($name && $this->subset != 'tags') {
			$this->allowMethods(array('GET'));
			
			$tagIDs = Zotero_Tags::getIDs($this->objectLibraryID, $name);
			if (!$tagIDs) {
				$this->e404();
			}
			
			$title = "Tags matching ‘" . $name . "’";
		}
		// All tags
		else {
			$this->allowMethods(array('GET', 'DELETE'));
			
			if ($this->scopeObject) {
				$this->allowMethods(array('GET'));
				
				switch ($this->scopeObject) {
					case 'collections':
						$collection = Zotero_Collections::getByLibraryAndKey($this->objectLibraryID, $this->scopeObjectKey);
						if (!$collection) {
							$this->e404();
						}
						$title =  "Tags in Collection ‘" . $collection->name . "’";
						$counts = $collection->getTagItemCounts();
						$tagIDs = array();
						if ($counts) {
							foreach ($counts as $tagID=>$count) {
								$tagIDs[] = $tagID;
								$fixedValues[$tagID] = array(
									'numItems' => $count
								);
							}
						}
						break;
						
					case 'items':
						$item = Zotero_Items::getByLibraryAndKey($this->objectLibraryID, $this->scopeObjectKey);
						if (!$item) {
							$this->e404();
						}
						$title = "Tags of '" . $item->getDisplayTitle() . "'";
						$tagIDs = $item->getTags(true);
						break;
					
					default:
						throw new Exception("Invalid tags scope object '$this->scopeObject'");
				}
			}
			else if ($this->method == 'DELETE') {
				// Filter for specific tags with "?tag=foo || bar"
				$tagNames = !empty($this->queryParams['tag'])
					? explode(' || ', $this->queryParams['tag']): array();
				Zotero_DB::beginTransaction();
				foreach ($tagNames as $tagName) {
					$tagIDs = Zotero_Tags::getIDs($this->objectLibraryID, $tagName);
					foreach ($tagIDs as $tagID) {
						$tag = Zotero_Tags::get($this->objectLibraryID, $tagID, true);
						Zotero_Tags::delete($this->objectLibraryID, $tag->key);
					}
				}
				Zotero_DB::commit();
				$this->e204();
			}
			else {
				$title = "Tags";
				$results = Zotero_Tags::search($this->objectLibraryID, $this->queryParams);
			}
		}
		
		if ($tagIDs) {
			$this->queryParams['tagIDs'] = $tagIDs;
			$results = Zotero_Tags::search($this->objectLibraryID, $this->queryParams);
		}
		
		if ($results && isset($results['results'])) {
			$totalResults = $results['total'];
			$results = $results['results'];
		}
		
		$this->responseXML = Zotero_Atom::createAtomFeed(
			$this->getFeedNamePrefix($this->objectLibraryID) . $title,
			$this->uri,
			$results,
			$totalResults,
			$this->queryParams,
			$this->permissions,
			$fixedValues
		);
		
		$this->end();
	}
}
