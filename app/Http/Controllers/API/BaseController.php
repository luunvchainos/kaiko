<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\ApiStatus;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Validator;
use Lang;
/**
 * Class BaseController
 * @package App\Http\Controllers\API
 */
class BaseController extends Controller
{
	/** This modal
	 *
	 * @var $modal
	 */
	protected $modal;
	
	/**
	 * @var $userInfo
	 */
	protected $userInfo = null;

	/** This resource
	 *
	 * @var
	 */
	protected $resource;

	/** Pagination number
	 *
	 * @var int
	 */
	protected $pagination = 10;

	/** Error message : No object after find
	 * @var string
	 */
	protected $error_msg_no_object;
	
	/**
	 * BaseController constructor.
	 */
    public function __construct()
    {
        $this->error_msg_no_object = Lang::get('api.no_object');
    }
	
	/**
	 * @param string $name
	 * @return \Illuminate\Contracts\Auth\Authenticatable|null
	 */
	protected function getUserLogin($name = '')
	{
		return $name ? Auth::user()[$name] : Auth::user();
	}

	/**
	 * @return mixed
	 */
	public function getUserInfo()
	{
		$this->userInfo = $this->userInfo ? $this->userInfo : (new User())->getUserInfo(['id' => Auth::id()]);
		
		return $this->userInfo;
	}

	/** Check empty specified resource in storage.
	 *
	 * @param $id
	 * @return bool
	 */
	protected function checkIdRequest($id)
	{
		$field = $this->modal::find($id);

		return empty($field) ? false : $field;
	}
	
	/**
	 * isAdmin Role
	 */
	protected function isAdmin()
	{
		return Role::isAdmin($this->getUserInfo()['role']['name']);
	}
	
	/**
	 * isManger Role
	 */
	protected function isManger()
	{
		return Role::isManager($this->getUserInfo()['role']['name']);
	}
	
	/**
	 * isUser Role
	 */
	protected function isUser()
	{
		return Role::isUser($this->getUserInfo()['role']['name']);
	}
	
	/** Send success format
	 *
	 * @param $ret_data
	 * @param $success_code
	 * @return array
	 */
	protected function sendResponse($ret_data, $success_code)
	{
		return [
			'response' => [
				'data' => $ret_data,
				'status' => [
					'code' => $success_code . " : " . (new ApiStatus($success_code))->getHttpMsg(),
					'message' => 'success'
				]
			]
		];
	}

	/** Send error format
	 *
	 * @param $error_code
	 * @param $message
	 * @return array
	 */
	protected function sendError($error_code, $message)
	{
		return [
			'response' => [
				'status' => [
					'code' => $error_code . " : " . (new ApiStatus($error_code))->getHttpMsg(),
					'message' => $message
				]
			]
		];
	}

	/** Display the all resource in storage.
	 *
	 * @return array
	 */
	public function index()
	{
		return $this->sendResponse($this->resource::collection($this->modal::all()), 200);
	}

	/** Display the specified resource in storage.
	 *
	 * @param $id
	 * @return array
	 */
	public function show($id)
	{
		$object = $this->checkIdRequest($id);

		if ($object === false) {
			return $this->sendError(404, $this->error_msg_no_object);
		}

		return $this->sendResponse(new $this->resource($object), 200);
	}

	/** Create the specified resource in storage.
	 *
	 * @param Request $request
	 * @return array
	 */
	public function store(Request $request)
	{
		$input = $request->all();
		$validator = Validator::make($input, (new $this->modal)->fieldSetValidate());
		$input['last_update_by'] = $this->getUserLogin()->id;

		if ($validator->fails()) {
			return $this->sendError(404, $validator->errors());
		}

		$object = $this->modal::create($input)->toArray();
		$field = $this->modal::find($object['id']);

		return $this->sendResponse(new $this->resource($field), 201);
	}
	
	/**
	 * Update the specified resource in storage.
	 *
	 * @param $id
	 * @param \Illuminate\Http\Request $request
	 * @return array
	 */
	public function update($id, Request $request)
	{
		$input = $request->all();
		$rules = (new $this->modal)->fieldSetValidate($id);
        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
			return $this->sendError(404, $validator->errors());
		}

		$input['last_update_by'] = $this->getUserLogin()->id;
		$object = $this->checkIdRequest($id);

		if ($object === false) {
			return $this->sendError(404, $this->error_msg_no_object);
		}

		foreach ((new $this->modal)->get_field_table() as $field) {
			if (isset($input[$field])) {
				$object->$field = $input[$field];
			}
		}

		$object->save();

		return $this->sendResponse(new $this->resource($object), 201);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param Request $id
	 * @return array
	 */
	public function destroy($id)
	{
		$object = $this->checkIdRequest($id);

		if ($object === false) {
			return $this->sendError(404, $this->error_msg_no_object);
		}

		$object['is_deleted'] = true;

		$object->save();

		return $this->sendResponse(new $this->resource($object), 200);
	}

	/** Enable the specified resource in storage.
	 *
	 * @param $id
	 * @return array
	 */
	public function enable($id)
	{
		return $this->switch($id, true);
	}

	/** Disable the specified resource in storage.
	 *
	 * @param $id
	 * @return array
	 */
	public function disable($id)
	{
		return $this->switch($id, false);
	}

	/**
	 * @param $id
	 * @param bool $isEnable
	 * @return array
	 */
	protected function switch($id, $isEnable = false)
	{
		$object = $this->checkIdRequest($id);

		if ($object === false) {
			return $this->sendError(404, $this->error_msg_no_object);
		}

		$object->is_enable = $isEnable;
		$object->save();

		return $this->sendResponse(new $this->resource($object), 201);
	}

	/**
	 * @param $row
	 * @return int 0-create | 1-update
	 */
	protected function isUpdateOrCreate($row)
	{
		return $this->modal::where('id', $row[0])->first() ? 0 : 1;
	}

	/**
	 * @param $data
	 * @param $row
	 * @return mixed
	 */
	protected function convertRowToModal($data, $row)
	{
		foreach ($data as $index => $fieldName) {
			$data[$fieldName] = $row[$index];
		}

		return $data;
	}

    /**
     * @param $name string name of export file
     * @return string
     */
	protected function getExportFileName($name)
    {
        return sprintf(trans('exports.file.' . $name) . '_%s.csv', Carbon::now()->format('YmdHis'));
    }
}
