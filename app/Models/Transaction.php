<?php
namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class Transaction extends BaseModel
{

    use Notifiable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'transactions';
    protected $fillable = ['id', 'time', 'send_id', 'receipt_id', 'amount', 'created_at', 'updated_at', 'last_update_by', 'created_by'];

    protected  $field_require = ['user_id', 'friend_id'];

	/**
	 * One to one relation
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withDefault();
    }

    /** Validation Data
     * @param null $id
     * @return array
     */
    public function fieldSetValidate($id = null)
    {
        $key = 'required';
        $result = [];

        foreach ($this->field_require as $item) {
            $result[$item] = $key;
        }

        $result['username'] = "string|required|min:6|max:30|unique:users,username,$id,id,is_deleted,0";
        $result['email'] = "required|string|email|max:100|unique:users,email,$id,id,is_deleted,0";
        $result['kana_first_name'] = 'required|katakana';
        $result['kana_last_name'] = 'required|katakana';
        $result['phone'] = 'required|digits:10';
        $result['password'] = 'min:8|nullable|regex:' . User::REGEX;
        $result['co_password'] = 'same:password|nullable';

        return $result;
    }

	/**
	 * @param $input
	 * @param int $pagination
	 * @param $roleName
	 * @return mixed
	 */
    public function search($input, $roleName, $pagination = 10)
    {
    	if (Role::isUser($roleName)) {
    	    return [];
	    }

	    $query = self::where(function ($query) use ($input) {
		    foreach ($input as $key => $value) {
			    if ($value == null) {
				    continue;
			    }

			    switch ($key) {
				    case 'role_id':
				    case 'store_id':
					    $query = $query->where($key, $value);
					    break;
				    case 'username':
					    $query = $query->where(function ($query) use ($value) {
						    $query->where('first_name', 'LIKE', $value . '%');
						    $query->orWhere('last_name', 'LIKE', $value . '%');
						    $query->orWhere('kana_first_name', 'LIKE', $value . '%');
						    $query->orWhere('kana_last_name', 'LIKE', $value . '%');
						    $query->orWhere(DB::raw("CONCAT(`first_name`, '', `last_name`)"), 'LIKE', $value . '%');
						    $query->orWhere(DB::raw("CONCAT(`first_name`, ' ', `last_name`)"), 'LIKE', $value . '%');
						    $query->orWhere(DB::raw("CONCAT(`kana_first_name`, ' ', `kana_last_name`)"), 'LIKE', $value . '%');
						    $query->orWhere(DB::raw("CONCAT(`kana_first_name`, '', `kana_last_name`)"), 'LIKE', $value . '%');
					    });
					    break;
				    case 'f-id':
					    // group newest f-id by user_id create
					    $group_id = FNumber::whereRaw('id IN (select MAX(id) FROM f_numbers GROUP BY user_id)');
					    // Search name like test
					    $group_id = $group_id->select('user_id')->where('name', 'LIKE',
						    '%' . $value . '%')->get()->toArray();
					    if (!empty($group_id)) {
						    $query = $query->where(function ($query) use ($group_id) {
							    foreach ($group_id as $id) {
								    $query->orWhere('id', $id);
							    }
						    });
					    } else {
						    // query always null
						    $query = $query->where('id', '-1');
					    }
					    break;
				    case 'email':
				    case 'phone':
					    $query = $query->where($key, 'LIKE', '%' . $value . '%');
					    break;
				    default:
					    break;
			    }
		    }
	    });

	    $query = $query->where(self::getConditionManager());
	    $query = $query->where('id', '!=', self::getUserInfoLogin('id'));
        $query = $query->where('is_deleted', false)->orderBy('id', 'desc')->paginate($pagination);

	    return $query;
    }

}
