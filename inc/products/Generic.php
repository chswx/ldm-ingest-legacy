<?php
/* 
 * Generic product ingestor. Fallback for non-specific products.
 */

class GenericProduct extends NWSProduct {
	function parse() {
		// TODO: Write the parser here.
		
		// STEP 1: Pull in counties
		$this->parse_zones($this->get_product_text());

		// STEP 2: Parse out VTEC
		$this->parse_vtec();

		// STEP 3: Relay readiness
		// Relay hydrological and tropical products
		
		$valid_products = array('WS','ZR','IS','CF','FA','WI','WC','FF','FG','HF','HI','HU','TI','TR','SS','HT','WW');

		if (in_array($this->get_vtec_phenomena(), $valid_products)) {
			$this->properties['relay'] = true;
		}
		else {
			$this->properties['relay'] = false;
		}

		// FINAL: Return the properties array

		return $this->properties;
	}

	/**
     * Get the name of the product.
     * 
     * @return string Product name
     */
	function get_name() {
		return $this->get_name_from_vtec();	
	}

	/**
	 * Get expiration time from the product.
	 * 
	 * @return string Expiration time
	 */
	function get_expiry() {
		return $this->get_expiry_from_vtec();
	}
}
