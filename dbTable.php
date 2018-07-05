<?php

abstract class My_DbTable extends All_DbTable
{
    protected static $prefixe = '';
    protected static $dbTable = '';
    protected static $dbTablePK = '';
    protected $id;
    protected $_fields_name;

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    public function __toString()
    {
        return $this->getId();
    }

    public function __construct($id = '', array $param = [], $create = false)
    {
        //si on a l'id, on fait un select dans la base et on hydrate l'objet avec les données récupérées
        if ($id !== '') {
            $infos = self::getInfos($id);
            if ($infos === false) {
                throw new Exception('Impossible de récuperer les informations de la table ' . static::$dbTable . ', erreur : ' . All_Error::getLast() . ', id : ' . $id);
            }
            try {
                $this->hydrate($infos);
            } catch (Exception $e) {
                throw $e;
            }
            //si on a des paramètres et que create est sur true on insert les datas dans la base
        } elseif ($param !== [] && $create === true) {
            try {
                $existingData = static::getInfosByFields($param, [], true);
                if (!empty($existingData)) {
                    $this->setId($existingData->getId());
                    $this->updateInst($param);
                } else {
                    $this->insertData($param);
                }
            } catch (Exception $e) {
                throw $e;
            }
        } elseif ($id === '' && $param === [] && $create === false) {
            return $this;
        } else {
            //sinon on attribue les données contenues dans param à l'objet
            try {
                $this->hydrate($param);
            } catch
            (Exception $e) {
                throw $e;
            }
        }
        return $this;
    }

    /**
     * hydrate un objet avec un tableau de données dont les clés sont au format "PREF_KEY" et les utilise pour appeller la methode correspondante
     * @param array $donnees
     */
    protected function hydrate(array $donnees)
    {
        foreach ($donnees as $key => $value) {

            $key = str_replace(static::$prefixe . '_', '', $key);
            $key = str_replace('_', ' ', $key);
            $key = ucwords(strtolower($key));
            $key = str_replace(' ', '', $key);
            // On récupère le nom du setter correspondant à l'attribut.

            $method = 'set' . $key;
            // Si le setter correspondant existe.
            if (method_exists($this, $method)) {
                // On appelle le setter.
                $this->$method($value);
            }
        }
    }

    /**
     * Update de l'instance de l'objet en base
     * @param array $param contient les clés écrites de la même façon que les noms des colonnes en base
     * @return $this
     * @throws Exception
     */
    public function updateInst(array $param)
    {
        try {
            $this->hydrate($param);
        } catch (Exception $e) {
            throw $e;
        }
        $update = static::update($param, $this->getId());
        if ($update === false) {
            throw new Exception('Impossible de mettre à jour les informations de ' . static::$dbTable . ' pour l\'id : ' . $this->getId());
        }
        return $this;
    }

    /**
     * insert des données désirée en base tout en hydratant l'objet
     * @param array $param contient les clés écrites de la même façon que les noms des colonnes en base
     * @return $this
     * @throws Exception
     */
    protected function insertData(array $param)
    {
        try {
            $this->hydrate($param);
        } catch (Exception $e) {
            throw $e;
        }
        $infos = self::insert($param);
        if ($infos === false) {
            throw new Exception('Impossible d\'enregistrer les informations dans ' . static::$dbTable);
        }
        $this->setId($infos);
        return $this;
    }

    /**
     * récupère la liste des informations pour la pagination
     * @param int $pagineDeb
     * @param string $pagineNb
     * @return array|false
     */
    public static function getList($pagineDeb = 0, $pagineNb = '')
    {

        $db = All_Tools::getDb();

        $sql = 'SELECT SQL_CALC_FOUND_ROWS * FROM ' . static::$dbTable;

        /**
         * TODO::ajout d'un param orderBy
         */
//        array $orderBy = []
//        if ($orderBy !== []) {
//            static::orderByArray($sql, $orderBy);
//        }

        if (trim($pagineNb) != '') {
            $sql .= ' limit ' . $pagineDeb * $pagineNb . ', ' . $pagineNb;
        }

        //execution de la requete
        $datas = $db->fetchAll($sql);
        if ($datas === false) {
            mouchard($sql);
            All_Error::add("Impossible de récupérer les informations de " . static::$dbTable, "sql", __FILE__, __LINE__, __METHOD__, __CLASS__);
            throw new ErrorException('Impossible de récupérer les information de ' . static::$dbTable);
        }

        //creation du tableau contenant les valeurs de retour
        $retour = array(
            'TOTAL' => 0,
            'LISTE' => array()
        );

        if (count($datas) > 0) {
            $resnb = $db->query('SELECT FOUND_ROWS() as total');
            $row = $db->fetch(false, $resnb);
            $retour['TOTAL'] = $row['total'];
//            foreach ($datas as &$data) {
//                $data = new static('', $data);
//            }
            $retour['LISTE'] = $datas;
        }

        return $retour;
    }

    /**
     * extends de getListInfosByFields de All_DbTable
     * permet de transformer le tableau obtenu par la méthode parente en tableau d'objet
     * @param array $fields
     * @param array $orderBy
     * @param array $infos
     * @return static[]
     * @throws Exception
     */
    public static function getListInfosByFields($fields, $orderBy = array(), $infos = array())
    {
        $datas = parent::getListInfosByFields($fields, $orderBy, $infos);
        if ($datas === false) {
            throw new Exception(All_Error::getLast());
        }
        $return = [];
        foreach ($datas as $data) {
            try {
                $return[] = new static('', $data);
            } catch (Exception $e) {
                throw $e;
            }
        }
        return $return;
    }

    /**
     * @param array $fields
     * @param array $infos
     * @param bool $nullIfNotExist
     * @return null|static
     * @throws Exception
     */
    public static function getInfosByFields($fields, $infos = array(), $nullIfNotExist = false)
    {
        $data = parent::getInfosByFields($fields, $infos, $nullIfNotExist);

        if ($data === false) {
            throw new Exception(All_Error::getLast(), 1);
        } elseif ($data === null) {
            return null;
        } else {
            try {
                $return = new static('', $data);
            } catch (Exception $e) {
                throw new Exception($e->getMessage() . ' - Table : ' . static::$dbTable);
            }
            return $return;
        }
    }

    /**
     * permet de contruire le tableau IN () d'une requete sql
     * @param array $tab
     * @return string
     */
    protected static function constructInTab(array $tab)
    {
        $outData = '(';
        for ($i = 0; $i < count($tab); $i++) {
            if ($i == count($tab) - 1) {
                $outData .= $tab[$i];
            } else {
                $outData .= $tab[$i] . ', ';
            }
        }
        $outData .= ')';
        return $outData;
    }

    /**
     * Recupère la liste de tous les éléments d'une table
     * @param string $orderBy | Nom de la colonne
     * @param string $sens | sens de l'order by
     * @return array|bool|false
     */
    public static function getAll($orderBy = '', $sens = 'ASC')
    {
        global $_db;
        $sql = 'select * from ' . static::$dbTable;
        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $orderBy . ' ' . $sens;
        }
        $datas = $_db->fetchAll($sql);
        if ($datas === false) {
            return All_Error::add('Impossible de récupérer les informations de la table ' . static::$dbTable, 'sql', __FILE__, __LINE__, __METHOD__, __CLASS__);
        }
        return $datas;
    }

    /**
     * supprime l'instance de l'objet de la base
     * @return bool
     * @throws Exception
     */
    public function deleteInst()
    {
        $delete = static::delete($this->id);
        if ($delete === false) {
            throw new Exception(All_Error::getLast());
        }
        return $delete;
    }

    /**
     * retourne la liste des id contenus dans une collection d'objet
     * @param static[] $collection
     * @return array
     */
    public static function getListIdFromCollection(array $collection)
    {
        $outDatas = [];
        foreach ($collection as $item) {
            $outDatas[] = $item->getId();
        }
        return $outDatas;
    }

    /**
     * permet de générer la requète sql à partir d'un tableau de la forme [['order'=> 'ASC|DESC', 'by'=> 'field'], ...]
     * @param $sql
     * @param array $orderBy
     */
    protected static function orderByArray(&$sql, array $orderBy)
    {
        $sql .= ' ORDER BY ';
        for ($i = 0; $i < count($orderBy); $i++) {
            $sql .= $orderBy[$i]['by'] . ' ' . $orderBy[$i]['order'] . (($i == (count($orderBy) - 1)) ? '' : ', ');
        }
    }

    public function flush()
    {
        $datas = $this->_constructArray();
        if ($this->id === '' || is_null($this->id)) {
            $this->insertData($datas);
        } else {
            $this->updateInst($datas);
        }
    }

    public function _constructArray()
    {
        $vars = get_object_vars($this);
        $datas = [];
        $columns = $this->_getFieldsName();
        foreach ($vars as $name => $var) {
            $key = static::$prefixe . '_' . strtoupper($name);
            if (in_array($key, $columns)) {
                if ($name !== 'id') {
                    $datas[$key] = $var;
                }
            }
        }
        return $datas;
    }

    protected function _getFieldsName()
    {
        global $_db;
        if ($this->_fields_name === null) {
            $sql = "select column_name from information_schema.columns where table_name='" . static::$dbTable . "'";
            $datas = $_db->fetchAll($sql);
            if ($datas === false) {
                //mouchard($sql);
                throw new Exception('Impossible de récupérer les colonnes de la table ' . static::$dbTable);
            }
            foreach ($datas as &$data) {
                $data = $data['column_name'];
            }
            $this->_fields_name = $datas;
        }
        return $this->_fields_name;
    }

    public static function _getWhereClause(&$sql, $where)
    {
        if (strstr($sql, 'WHERE')) {
            $sql .= ' AND ' . $where;
        } else {
            $sql .= ' WHERE ' . $where;
        }
    }
}