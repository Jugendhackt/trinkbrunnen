<?php
/**
 * Changeset.php
 * 25-Apr-2011
 *
 * PHP Version 5
 *
 * @category Services
 * @package  Services_OpenStreetMap
 * @author   Ken Guest <kguest@php.net>
 * @license  BSD http://www.opensource.org/licenses/bsd-license.php
 * @version  Release: @package_version@
 * @link     Changeset.php
 */

/**
 * Services_OpenStreetMap_Changeset
 *
 * @category Services
 * @package  Services_OpenStreetMap
 * @author   Ken Guest <kguest@php.net>
 * @license  BSD http://www.opensource.org/licenses/bsd-license.php
 * @link     Changeset.php
 */
class Services_OpenStreetMap_Changeset extends Services_OpenStreetMap_Object
{
    protected $type = 'changeset';
    protected $members = array();
    protected $membersIds = array();
    protected $open = false;
    protected $id = null;
    protected $osmChangeXml = null;

    /**
     * __construct
     *
     * @return Services_OpenStreetMap_Changeset
     */
    public function __construct()
    {
    }

    /**
     * begin
     *
     * @param string $message The changeset log message.
     *
     * @return void
     * @throws Services_OpenStreetMap_RuntimeException If either user or
     *                                                 password are not set.
     */
    public function begin($message)
    {
        $this->members = array();
        $this->open = true;
        $config = $this->getConfig();
        $userAgent = $config->getValue('User-Agent');
        $doc = "<?xml version='1.0' encoding=\"UTF-8\"?>\n" .
        '<osm version="0.6" generator="' . $userAgent . '">'
            . "<changeset id='0' open='false'>"
            . '<tag k="comment" v="' . $message . '"/>'
            . '<tag k="created_by" v="' . $userAgent . '/0.1"/>'
            . '</changeset></osm>';
        $url = $config->getValue('server')
            . 'api/'
            . $config->getValue('api_version')
            . '/changeset/create';
        $user = $config->getValue('user');
        $password = $config->getValue('password');
        if (is_null($user)) {
            throw new Services_OpenStreetMap_RuntimeException('User must be set');
        }
        if (is_null($password)) {
            throw new Services_OpenStreetMap_RuntimeException(
                'Password must be set'
            );
        }
        $response = $this->getTransport()->getResponse(
            $url,
            HTTP_Request2::METHOD_PUT,
            $user,
            $password,
            $doc,
            null,
            array(array('Content-type', 'text/xml', true))
        );
        $code = $response->getStatus();
        if (Services_OpenStreetMap_Transport::OK == $code) {
            $trimmed = trim($response->getBody());
            if (is_numeric($trimmed)) {
                $this->id = $trimmed;
            }
        }
    }

    /**
     * add object to the changeset so changes can be transmitted to the server
     *
     * @param Services_OpenStreetMap_Object $object OSM object
     *
     * @return void
     * @throws Services_OpenStreetMap_RuntimeException If an object has already
     *                                                 been added to the changeset
     *                                                 or has been added to a
     *                                                 closed changeset.
     */
    public function add(Services_OpenStreetMap_Object $object)
    {
        if ($this->open === false) {
            throw new Services_OpenStreetMap_RuntimeException(
                'Object added to closed changeset'
            );
        }
        $object->setChangesetId($this->getId());
        $objectId = $object->getType() . $object->getId();
        if (!in_array($objectId, $this->membersIds)) {
            $this->members[] = $object;
            $this->membersIds[] = $objectId;
        } else {
            throw new Services_OpenStreetMap_RuntimeException(
                'Object added to changeset already'
            );
        }
    }

    /**
     * commit
     *
     * Generate osmChange document and post it to the server, when successful
     * close the changeset.
     *
     * @return void
     * @link   http://wiki.openstreetmap.org/wiki/OsmChange
     * @throws Services_OpenStreetMap_RuntimeException If changeset Id is not
     *                                                 numeric
     */
    public function commit()
    {
        if (!$this->open) {
            throw new Services_OpenStreetMap_Exception(
                'Attempt to commit a closed changeset'
            );
        }

        // Generate URL that the osmChange document will be posted to
        $cId = $this->getId();
        if (!is_numeric($cId)) {
            if ($cId !== null) {
                $msg = 'Changeset ID of unexpected type. (';
                $msg .= var_export($cId, true) . ')';
                throw new Services_OpenStreetMap_RuntimeException($msg);
            }
        }
        $config = $this->getConfig()->asArray();
        $url = $config['server']
            . 'api/'
            . $config['api_version'] .
            "/changeset/{$cId}/upload";

        // Post the osmChange document to the server
        try {
            $response = $this->getTransport()->getResponse(
                $url,
                HTTP_Request2::METHOD_POST,
                $config['user'],
                $config['password'],
                $this->getOsmChangeXml(),
                null,
                array(array('Content-type', 'text/xml', true))
            );
            $this->updateObjectIds($response->getBody());
        } catch (Exception $ex) {
            $code = $ex->getCode();
        }

        if (isset($response) && is_object($response)) {
            $code = $response->getStatus();
        }
        if (Services_OpenStreetMap_Transport::OK != $code) {
            throw new Services_OpenStreetMap_Exception(
                'Error posting changeset',
                $code
            );

        }
        // Explicitly close the changeset
        $url = $config['server']
            . 'api/'
            . $config['api_version']
            . "/changeset/{$cId}/close";

        $code = null;
        $response = null;
        try {
            $response = $this->getTransport()->getResponse(
                $url,
                HTTP_Request2::METHOD_PUT,
                $config['user'],
                $config['password'],
                null,
                null,
                array(array('Content-type', 'text/xml', true))
            );
        } catch (Exception $ex) {
            $code = $ex->getCode();
        }
        if (isset($response) && is_object($response)) {
            $code = $response->getStatus();
        }
        if (Services_OpenStreetMap_Transport::OK != $code) {
            throw new Services_OpenStreetMap_Exception(
                'Error closing changeset',
                $code
            );
        }
        $this->open = false;
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
        if (is_null($this->osmChangeXml)) {

            // Generate the osmChange document
            $blocks = null;
            foreach ($this->members as $member) {
                $blocks .= $member->getOsmChangeXml() . "\n";
            }
            $this->setOsmChangeXml("<osmChange version='0.6' generator='Services_OpenStreetMap'>" . $blocks . '</osmChange>');
        }
        return $this->osmChangeXml;
    }

    /**
     * setOsmChangeXml
     *
     * @param string $xml OsmChange XML
     *
     * @return Services_OpenStreetMap_Changeset
     */
    public function setOsmChangeXml($xml)
    {
        $this->osmChangeXml = $xml;
        return $this;
    }

    /**
     * getCreatedAt
     *
     * @return string
     */
    public function getCreatedAt()
    {
        return (string) $this->getAttributes()->created_at;
    }

    /**
     * getClosedAt
     *
     * @return string
     */
    public function getClosedAt()
    {
        return (string) $this->getAttributes()->closed_at;
    }

    /**
     * isOpen
     *
     * @return boolean
     */
    public function isOpen()
    {
        $attribs = $this->getAttributes();
        if (!is_null($attribs)) {
            return $attribs->open == 'true';
        } else {
            return $this->open;
        }
    }

    /**
     * getMinLon
     *
     * @return float
     */
    public function getMinLon()
    {
        return (float) $this->getAttributes()->min_lon;
    }

    /**
     * getMinLat
     *
     * @return float
     */
    public function getMinLat()
    {
        return (float) $this->getAttributes()->min_lat;
    }


    /**
     * getMaxLon
     *
     * @return float
     */
    public function getMaxLon()
    {
        return (float) $this->getAttributes()->max_lon;
    }

    /**
     * getMaxLat
     *
     * @return float
     */
    public function getMaxLat()
    {
        return (float) $this->getAttributes()->max_lat;
    }


    /**
     * getId
     *
     * @return numeric value or null if none set
     */
    public function getId()
    {
        $p_id = parent::getId();
        if (is_null($p_id)) {
            return $this->id;
        } else {
            return $p_id;
        }
    }

    /**
     * Given diffResult xml, update Ids of objects that are members of the
     * current changeset.
     *
     * @param string $body diffResult xml
     *
     * @return void
     */
    public function updateObjectIds($body)
    {
        $body = trim($body);
            // should check here that body has expected form.
        if (stripos($body, 'diffResult') === false ) {
            throw new Services_OpenStreetMap_Exception('Invalid diffResult XML');
        }
        $cxml = simplexml_load_string($body);
        $obj = $cxml->xpath('//diffResult');
        foreach ($obj[0]->children() as $child) {
            $old_id = null;
            $new_id = null;
            $old_id = (string) $child->attributes()->old_id;
            $new_id = (string) $child->attributes()->new_id;
            $this->updateObjectId($child->getName(), $old_id, $new_id);
        }
    }

    /**
     * Update id of some type of object
     *
     * @param string  $type   Object type
     * @param integer $old_id Old id
     * @param integer $new_id New id
     *
     * @return void
     */
    public function updateObjectId($type, $old_id, $new_id)
    {
        if ($old_id == $new_id) {
            return;
        }
        foreach ($this->members as $member) {
            if ($member->getType() == $type) {
                if ($member->getId() == $old_id) {
                    $member->setId($new_id);
                }
            }
        }
    }
}
// vim:set et ts=4 sw=4:
?>
