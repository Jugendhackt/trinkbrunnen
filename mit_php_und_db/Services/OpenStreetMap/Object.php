<?php
/**
 * Object.php
 * 26-Apr-2011
 *
 * PHP Version 5
 *
 * @category Services
 * @package  Services_OpenStreetMap
 * @author   Ken Guest <kguest@php.net>
 * @license  BSD http://www.opensource.org/licenses/bsd-license.php
 * @version  Release: @package_version@
 * @link     Object.php
 */

/**
 * Services_OpenStreetMap_Object
 *
 * @category Services
 * @package  Services_OpenStreetMap
 * @author   Ken Guest <kguest@php.net>
 * @license  BSD http://www.opensource.org/licenses/bsd-license.php
 * @link     Object.php
 */
class Services_OpenStreetMap_Object
{
    protected $xml = null;

    /**
     * Array of tags in key/value format
     *
     * @var array
     */
    protected $tags = array();

    protected $id = null;

    protected $transport = null;

    protected $config = null;

    /**
     * type of object
     *
     * @var string
     */
    protected $type = null;

    protected $obj = null;

    protected $dirty = false;

    protected $action = null;

    protected $changesetId = null;

    /**
     * getXml
     *
     * @return string
     */
    public function getXml()
    {
        return $this->xml;
    }

    /**
     * If modified, return the osmChangeXML for the object, otherwise the defining
     * XML.
     *
     * @return string
     * @link   http://wiki.openstreetmap.org/wiki/OsmChange
     */
    public function __toString()
    {
        $changeXML = $this->getOsmChangeXml();
        if (is_null($changeXML)) {
            return '' . $this->getXml();
        } else {
            return $changeXML;
        }
    }

    /**
     * setXml
     *
     * @param SimpleXMLElement $xml OSM XML
     *
     * @return void
     */
    public function setXml(SimpleXMLElement $xml)
    {
        $this->xml = $xml->saveXml();
        $obj = $xml->xpath('//' . $this->getType());
        foreach ($obj[0]->children() as $child) {
            $key = (string) $child->attributes()->k;
            if ($key != '') {
                $this->tags[$key] = (string) $child->attributes()->v;
            }
        }
        $this->obj = $obj;
        return $this;
    }

    /**
     * Store a specified value.
     *
     * @param string $value Most likely an id value, returned from the server.
     *
     * @return void
     */
    public function setVal($value)
    {
        $this->xml = $value;
        return $this;
    }

    /**
     * Set the Changeset Id for this object.
     *
     * @param integer $id Changeset Id (numeric)
     *
     * @return Services_OpenStreetMap_Object
     */
    public function setChangesetId($id)
    {
        $this->changesetId = $id;
        return $this;
    }

    /**
     * Generate and return the OsmChange XML required to record the changes
     * made to the object in question.
     *
     * @return string
     * @link   http://wiki.openstreetmap.org/wiki/OsmChange
     */
    public function getOsmChangeXml()
    {
        $type = $this->getType();
        if ($this->dirty) {
            $version = $this->getVersion();
            $version++;
            $domd = new DomDocument();
            $domd->loadXml($this->getXml());
            $xpath = new DomXPath($domd);
            $nodelist = $xpath->query("//{$type}");
            $nodelist->item(0)->setAttribute('action', $this->action);
            $nodelist->item(0)->setAttribute('id', $this->getId());

            if (!is_null($this->changesetId)) {
                $nodelist->item(0)->setAttribute('changeset', $this->changesetId);
            }
            $tags = $xpath->query("//{$type}/tag");

            $set = array();
            for ($i = 0; $i < $tags->length; $i++) {
                $key = $tags->item($i)->getAttribute('k');
                $val = $tags->item($i)->getAttribute('v');
                $set[$key] = $val;
            }

            $diff = array_diff($this->getTags(), $set);

            // Remove existing tags
            for ($i = 0; $i < $tags->length; $i++) {
                $rkey = $tags->item($i)->getAttribute('k');
                if (isset($diff[$rkey])) {
                    $nodelist->item(0)->removeChild($tags->item($i));
                }
            }

            foreach ($diff as $key=>$value) {
                $new = $domd->createElement('tag');
                $new->setAttribute('k', $key);
                $new->setAttribute('v', $value);
                $nodelist->item(0)->appendChild($new);
            }

            $xml = $domd->saveXml($nodelist->item(0));
            $xml = "<{$this->action}>{$xml}</{$this->action}>";
            return $this->osmChangeXml($xml);

        } elseif ($this->action == 'delete') {
            $xml = null;
            $domd = new DomDocument();
            $domd->loadXml($this->getXml());
            $xpath = new DomXPath($domd);
            $n = $xpath->query("//{$type}");
            $version = $this->getVersion();
            $version++;
            if (!is_null($this->changesetId)) {
                $n->item(0)->setAttribute('changeset', $this->changesetId);
            }
            $n->item(0)->setAttribute('action', 'delete');
            $xml = $domd->saveXml($n->item(0));
            return $this->osmChangeXml("<delete>{$xml}</delete>");
        }
    }

    /**
     * Amend changeXML with specific updates as appropriate.
     *
     * @param string $xml OsmChange XML as generated by getOsmChangeXml
     *
     * @return string
     * @see    getOsmChangeXml
     * @link   http://wiki.openstreetmap.org/wiki/OsmChange
     */
    public function osmChangeXml($xml)
    {
        return $xml;
    }

    /**
     * Retrieve the id of the object in question.
     *
     * @return integer id of the object
     */
    public function getId()
    {
        if (!is_null($this->id)) {
            return $this->id;
        }

        $attribs = $this->getAttributes();
        if (!is_null($attribs)) {
            return (integer) $attribs->id;
        }
    }

    /**
     * Set the id value of the object in question.
     *
     * <pre>
     * $obj->setId($id)->...
     * </pre>
     *
     * @param integer $value new id of the object
     *
     * @return Services_OpenStreetMap_Object
     */
    public function setId($value)
    {
        $this->id = $value;
        return $this;
    }

    /**
     * Retrieve the uid of the object in question.
     *
     * @return integer uid of the object
     */
    public function getUid()
    {
        $attribs = $this->getAttributes();
        if (!is_null($attribs)) {
            return (integer) $attribs->uid;
        }
    }

    /**
     * Retrieve the user (creator/editor) of the object in question.
     *
     * @return string user of the object
     */
    public function getUser()
    {
        $attribs = $this->getAttributes();
        if (!is_null($attribs)) {
            return (string) $attribs->user;
        }
    }

    /**
     * Retrieve the version of the object in question
     *
     * @return string version of the object
     */
    public function getVersion()
    {
        $attribs = $this->getAttributes();
        if (!is_null($attribs)) {
            return (integer) $attribs->version;
        }
    }

    /**
     * Return the attributes set for this object in question.
     *
     * @return string getAttributes()
     */
    public function getAttributes()
    {

        if (is_null($this->obj[0])) {
            return null;
        }
        return $this->obj[0]->attributes();
    }

    /**
     * Return the tags set for this object in question.
     *
     * @return array tags
     */
    public function getTags()
    {
        return $this->tags;
    }


    /**
     * Return value of specified tag as set against this object.
     * If tag isn't set, return null.
     *
     * @param string $key Key value, For example, 'amenity', 'highway' etc
     *
     * @return string
     */
    public function getTag($key)
    {
        if (isset($this->tags[$key])) {
            return $this->tags[$key];
        } else {
            return null;
        }
    }

    /**
     * Return which type of object this is.
     *
     * @return string type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get each distinct version of an object.
     *
     * @return Services_OpenStreetMap_Objects
     */
    public function history()
    {
        $transport = null;
        $type = $this->getType();
        $id = $this->getId();
        $config = $this->getConfig();
        $url = $config->getValue('server')
            . 'api/'
            . $config->getValue('api_version')
            . "/$type/$id/history";
        $class = 'Services_OpenStreetMap_' . ucfirst($type) . 's';
        $transport = $this->getTransport();
        $response = $transport->getResponse($url);
        $obj = new $class();
        $sxe = @simplexml_load_string($response->getBody());
        if ($sxe === false) {
            $obj->setVal(trim($response->getBody()));
        } else {
            $obj->setXml($sxe);
        }
        return $obj;
    }

    /**
     * Get all relations referring to the object in question.
     *
     * @return Services_OpenStreetMap_Relations
     */
    public function getRelations()
    {
        $type = $this->getType();
        $id = $this->getId();
        $config = $this->getConfig();
        $url = $config->getValue('server')
            . 'api/'
            . $config->getValue('api_version')
            . "/$type/$id/relations";
        $response = $this->getTransport()->getResponse($url);
        $obj = new Services_OpenStreetMap_Relations();
        $sxe = @simplexml_load_string($response->getBody());
        if ($sxe === false) {
            $obj->setVal(trim($response->getBody()));
        } else {
            $obj->setXml($sxe);
        }
        return $obj;
    }

    /**
     * setTag
     *
     * <pre>
     * $obj->setTag('key', 'value')->setTag(...);
     * </pre>
     *
     * @param mixed $key   key
     * @param mixed $value value
     *
     * @return Services_OpenStreetMap_Object
     */
    public function setTag($key, $value)
    {
        if (is_null($this->action)) {
            if ($this->getId() < 0) {
                $this->action = 'create';
            } else {
                $this->action = 'modify';
            }
        }
        $this->dirty = true;
        $this->tags[$key] = $value;
        return $this;
    }

    /**
     * Set a number of tags at once, using an associative array.
     *
     * <pre>
     * $obj->setTag(
     *  array(
     *   'key' => 'value',
     *   'key2', 'value2',
     *  )
     * );
     * </pre>
     *
     * @param array $tags array of tags.
     *
     * @return Services_OpenStreetMap_Object
     */
    public function setTags($tags = array())
    {
        foreach ($tags as $key => $value) {
            $this->setTag($key, $value);
        }
        return $this;
    }

    /**
     * Mark the object as deleted.
     *
     * <pre>
     * $obj->delete();
     * </pre>
     *
     * @return void
     */
    public function delete()
    {
        $this->action = 'delete';
        return $this;
    }

    /**
     * Set Config object
     *
     * @param Services_OpenStreetMap_Config $config Config object
     *
     * @return Services_OpenStreetMap_Changeset
     */
    public function setConfig(Services_OpenStreetMap_Config $config)
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Get current Config object
     *
     * @return Services_OpenStreetMap_Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set the Transport instance.
     *
     * @param Services_OpenStreetMap_Transport $transport Transport instance.
     *
     * @return Services_OpenStreetMap_Config
     */
    public function setTransport($transport)
    {
        $this->transport = $transport;
        return $this;
    }

    /**
     * Retrieve the current Transport instance.
     *
     * @return Services_OpenStreetMap_Transport.
     */
    public function getTransport()
    {
        return $this->transport;
    }
}

// vim:set et ts=4 sw=4:
?>
