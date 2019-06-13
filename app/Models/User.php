<?php
namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class User extends BaseModel implements AuthenticatableContract, CanResetPasswordContract
{

    use Authenticatable, CanResetPassword, Notifiable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    const REGEX = '/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/';
    protected $table = 'users';
    protected $fillable = ['id','company_code', 'role_type', 'username','email','password','first_name','last_name','kana_first_name',
                            'kana_last_name','comment','created_at','updated_at'];

    protected  $field_require = ['email', 'first_name', 'last_name', 'kana_first_name', 'kana_last_name', 'role_type', 'company_code' ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];

	/**
	 * One to Many relation
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
    public function role()
    {
        return $this->belongsTo('App\Models\Role')->withDefault();
    }

	/**
	 * Many to One relation
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
    public function f_num()
    {
        return $this->hasMany('App\Models\FNumber');
    }

	/**
	 * One to Many relation
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
    public function store()
    {
        return $this->belongsTo('App\Models\Store');
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
     * @return array validate
     */
    public function fieldSetValidateChangePass()
    {
        $result = [];

        $result['current_password'] = 'required';
        $result['password'] = 'required|different:current_password|confirmed|min:8|regex:' . User::REGEX;

        return $result;
    }

	/**
	 * @param array $condition
	 * @return mixed
	 */
    public function getUserInfo($condition)
    {
    	return User::where($condition)->with('role')->first();
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


    /**
     * @param $roleName
     * @param array $condition
     * @return array
     */
    public static function getConditionManager($condition = [], $roleName = '')
    {
        if (empty($roleName)) {
            $roleName = Role::getRoleInfoWithRoleId(User::getUserInfoLogin('role_id'))->name;
        }

        if (Role::isManager($roleName)) {
            $condition['store_id'] = self::getUserInfoLogin('store_id');
        }

        return $condition;
    }

    /**
     * @param string $name
     * @return AuthenticatableContract|null
     */
    public static function getUserInfoLogin($name = '')
    {
        return $name ? Auth::user()[$name] : Auth::user();
    }

}
