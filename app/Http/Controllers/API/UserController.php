<?php

namespace App\Http\Controllers\API;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Models\FNumber;
use DB;
use Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Exports\UsersExport;
use Lang;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */
class UserController extends BaseController
{
	/** Overwrite this modal
	 *
	 * @var string
	 */
	protected $modal = 'App\Models\User';

	/** Overwrite this resource
	 *
	 * @var string
	 */
	protected $resource = 'App\Http\Resources\UserResource';

	/** Collection resource for pagination
	 *
	 * @var string
	 */
	private $collection = 'App\Http\Resources\UserCollection';

	/**
	 * @var $default_txt
	 */
	private $default_txt;

	/**
	 * UserController constructor.
	 */
	public function __construct()
	{
		parent::__construct();
		$this->default_txt = Lang::get('api.default');
	}

	/** Auto generate f-id string name
	 * @return string
	 */
	private function generator_fid()
	{
		return str_random(20);
	}

	/** Search the specified resource in storage by key.
	 *
	 * @param Request $request
	 * @return array
	 */
	public function search(Request $request)
	{
		if ($this->isUser()) {
			return $this->sendError(401, trans('validation.custom.import.no_data'));
		}

		return $this->sendResponse(new $this->collection(
			(new $this->modal)->search($request->all(), $this->userInfo['role']['name'], $this->pagination)
		), 200);
	}

	/** Overwrite display the all resource in storage.
	 * @return array
	 */
	public function index()
	{
		if ($this->isUser()) {
			return $this->sendError(401, trans('validation.custom.import.no_data'));
		}

		$query = $this->modal::select()->where(User::getConditionManager());
		$query = $query->where('id', '!=', $this->getUserLogin('id'));

		return $this->sendResponse(
			new $this->collection(
				(new $this->modal)->default_query($query, $this->pagination)
			), 200);
	}

	/** Overwrite create the specified resource in storage.
	 *
	 * @param Request $request
	 * @return array
	 */
	public function store(Request $request)
	{
		$input = $request->all();
		$validator = Validator::make($input, (new $this->modal)->fieldSetValidate());

		if ($validator->fails()) {
			return $this->sendError(404, $validator->errors());
		}

		$input['password'] = bcrypt($input['password']);
		$input['last_update_by'] = Auth::id();
		$input['parent_user'] = Auth::id();

		$object = $this->modal::create($input)->toArray();
		$field = $this->modal::find($object['id']);

		// auto create f_id
		$f_number = new FNumber();
		$f_number->name = $this->generator_fid();
		$f_number->memo = $this->default_txt;
		$f_number->user_id = $object['id'];
		$f_number->save();

		return $this->sendResponse(new $this->resource($field), 201);
	}

	/**
	 * @return array
	 */
	public function getListManager()
	{
		if ($this->isUser()) {
			return $this->sendError(401, trans('validation.custom.import.no_data'));
		}

		return $this->sendResponse($this->resource::collection($this->modal::where('role_id', 3)->get()), 200);
	}

	/**
	 * @return array|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
	 */
	public function exports()
	{
		if ($this->isUser()) {
			return $this->sendError(401, trans('validation.custom.import.no_data'));
		}

		return (new UsersExport)->download($this->getExportFileName('user'));
	}

	/**
	 * @param Request $request
	 * @return array
	 */
	public function import(Request $request)
	{
		if ($this->isUser()) {
			return $this->sendError(401, trans('validation.custom.import.no_data'));
		}

		try {
			$data = \GuzzleHttp\json_decode($request->csv);
			if ($request->count < 14) {
				return $this->sendError(404, trans('validation.custom.import.no_data'));
			}

			$csv_data = array_slice($data, 1);
			$rowSuccess = collect();
			$rowErrors = collect();

			foreach ($csv_data as $index => $row) {
				$userRow = $this->convertRowToUser($row);
				$checked = $this->isUpdateOrCreate($row);
				$rules = (new $this->modal)->fieldSetValidate($row[0]);
				$validatorUser = Validator::make($userRow, $rules);

				if ($validatorUser->fails()) {
					$rowErrors->push(['index' => $index, 'message' => $validatorUser->messages()]);
					continue;
				}

				DB::beginTransaction();
				$user = $checked == 0 ? User::find($row[0]) : new User();

				try {
					$user_id = Auth::id();
					$user->last_update_by = $user_id;

					if ($checked == 1) {
						$user->parent_user = $user_id;
					}

					$user->username = $userRow['username'];
					$user->email = $userRow['email'];
					$user->first_name = $userRow['first_name'];
					$user->last_name = $userRow['last_name'];
					$user->kana_first_name = $userRow['kana_first_name'];
					$user->kana_last_name = $userRow['kana_last_name'];
					$user->phone = $userRow['phone'];
					$user->store_id = $userRow['store_id'];

					if ($this->isAdmin()) {
						$user->role_id = $userRow['role_id'];
						$user->parent_user = $userRow['parent_user'] ? $userRow['parent_user'] : $user_id;
					}

					if ($this->isManger()) {
						$user->role_id = Role::getRole(['name' => Role::ROLE_NAME['user']])->id;
					}

					$user->zip_code = $userRow['zip_code'];
					$user->comment = $userRow['comment'];
					$user->is_enable = $userRow['is_enable'] ? $userRow['is_enable'] : 1;
					$user->password = bcrypt('12345678');
					$user->save();

					if ($checked == 1) {
						$f_number = new FNumber();
						$f_number->name = $this->generator_fid();
						$f_number->memo = $this->default_txt;
						$f_number->user_id = $user->id;
						$f_number->save();
					}

					DB::commit();
					$rowSuccess->push(['index' => $index]);
				} catch (\Exception $e) {
					DB::rollback();
					$rowErrors->push(['index' => $index, 'message' => $e->getMessage()]);
				}
			}

			return $this->sendResponse([
				'row' => count($csv_data),
				'rowSuccess' => $rowSuccess,
				'rowError' => $rowErrors
			], 200);
		} catch (Exception $e) {
			return $this->sendError(404, $e->getMessage());
		}
	}

	/**
	 * @param $row
	 * @return mixed
	 */
	protected function convertRowToUser($row)
	{
		return $this->convertRowToModal([
			'id',
			'username',
			'email',
			'first_name',
			'last_name',
			'kana_first_name',
			'kana_last_name',
			'store_id',
			'role_id',
			'phone',
			'zip_code',
			'comment',
			'parent_user',
			'is_enable'
		], $row);
	}

}
