<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The field array of table.
     *
     * @var array
     */
    protected $fillable;

    /**
     * The field array need require of table.
     *
     * @var array
     */
    protected $field_require;

    /** Validation Data
     * @return array
     */

    public function parseData($table_modal, $current_obj, $obj)
    {
        foreach ((new $table_modal)->get_field_table() as $field) {
            if (isset($obj[$field])) {
                if( ($field != 'id') && ($field != 'created_at') && ($field != 'updated_at'))
                {
                    $current_obj->$field = $obj[$field];
                }
            }
        }
    }

    public function copy_data($table_modal, $key, $value, $new_value)
    {
        $list_search = $table_modal::where($key, $value)
            ->where('is_deleted', false)->get()->toarray();

        foreach ($list_search as $search) {
            if (!empty($search)) {
                $new = new $table_modal;
                $this->parseData($table_modal, $new, $search);
                $new->$key = $new_value;
                $table_modal::create($new->toArray());
            }
        }
    }

    public function fieldSetValidate()
    {
        $key = 'required';
        $result = [];

        foreach ($this->field_require as $item) {
            $result[$item] = $key;
        }

        return $result;
    }

    /** Validation Data
     * @return array
     */
    public function  get_field_table()
    {
        return $this->fillable;
    }

    /** Update data table with foreign key
     * @param $input
     * @param $id
     * @param $table_modal
     * @param $key1
     * @param $key2
     */
    public function update_foreign_data($input, $id, $table_modal, $key1, $key2, $optional = NULL)
    {
        $list_obj = array();
        foreach ($input as $obj) {
            if(isset($optional) && $optional == 'plan'){
                if($obj['selected'] === false)
                    continue;
            }
            // check if record none exits to create
            $search = $table_modal::where($key1, $id)
                ->where($key2, $obj['id'])->where('is_deleted', false)->get()->toarray();

            if(empty($search)) {
                $modal = new $table_modal;
                $modal->$key1 = $id;
                $modal->$key2 = $obj['id'];
                $this->parseData($table_modal, $modal, $obj);
                $modal->save();
                $modal = null;
            }
            array_push($list_obj, $obj['id']);
        }

        // find and remove old record
        $old_record = $table_modal::where($key1,$id)->where('is_deleted', false)->get()->toarray();
        if(!empty($old_record)) {
            foreach ($old_record as $old) {
                if (!in_array($old[$key2], $list_obj)) {
                    $delete_obj = $table_modal::find($old['id']);
                    $delete_obj->is_deleted = true;
                    $delete_obj->save();
                }
            }
        }
    }

    /** Update data table with relation
     * @param $input
     * @param $id
     * @param $table_modal
     * @param $key
     */
    public function update_relation_data($input, $id, $table_modal, $key)
    {
        $list_obj = array();
        foreach ($input as $obj) {
            // update
            if(!empty($obj['id'])) {
                $current_obj = $table_modal::find($obj['id']);
                if(isset($current_obj)) {
                    $this->parseData($table_modal, $current_obj, $obj);
                    $current_obj->save();
                }
                array_push($list_obj, $obj['id']);
            }
            // create
            else {
                $modal = new $table_modal;
                $modal->$key = $id;
                $this->parseData($table_modal, $modal, $obj);
                $modal->save();

                array_push($list_obj, $modal['id']);
            }
        }

        // find and remove old record
        $old_record = $table_modal::where($key,$id)->where('is_deleted', false)->get()->toarray();
        if(!empty($old_record)) {
            foreach ($old_record as $old) {
                if (!in_array($old['id'], $list_obj))
                {
                    $delete_obj = $table_modal::find($old['id']);
                    $delete_obj->is_deleted = true;
                    $delete_obj->save();
                }
            }
        }
    }

    /** Create data table with foreign key
     * @param $input
     * @param $id
     * @param $table_modal
     * @param $key1
     * @param $key2
     */
    public function  create_foreign_data($input, $id, $table_modal, $key1, $key2)
    {
        foreach ($input as $obj) {
            $modal = new $table_modal;
            $modal->$key1 = $id;
            $modal->$key2 = $obj['id'];
            $this->parseData($table_modal, $modal, $obj);
            $modal->save();
            $modal = null;
        }
    }

    /** Create data table with relation
     * @param $input
     * @param $id
     * @param $table_modal
     * @param $key
     */
    public function create_relation_data($input, $id, $table_modal, $key)
    {
        foreach ($input as $obj) {
            $modal = new $table_modal;
            $modal->$key = $id;
            $this->parseData($table_modal, $modal, $obj);
            $modal->save();
            $modal = null;
        }
    }

    public function default_query($query, $pagination)
    {
        return $query->orderBy('id', 'desc')->paginate($pagination);
    }

    public function default_query_list($query)
    {
        return $query->orderBy('id', 'desc')->get();
    }
}
