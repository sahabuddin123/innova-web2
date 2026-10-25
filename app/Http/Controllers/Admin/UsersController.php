<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Controllers\BaseController;
use App\Models\User;
use App\Models\Admin;
use App\Models\Students;
use Spatie\Permission\Models\Role;
use DB;
use Hash;

class UsersController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data = User::orderBy('id','DESC')->paginate(5);
        $this->setPageTitle('User Management', 'List of all users');
        return view('admin.users.index',compact('data')) ->with('i', ($request->input('page', 1) - 1) * 5);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $student = Students::where('deleted_at', null)->get();
        $this->setPageTitle('User Management', 'Create a new user');
        return view('admin.users.create',compact('student'));
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            // 'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|same:confirm-password',
            'student_id' => 'required'
        ]);

        $input = $request->all();
        $username = Students::where('id', $request->student_id)->first();
        $input['name'] = $username->sname;
        $input['password'] = Hash::make($input['password']);

        $user = User::create($input);
        $students = Students::where('id', $request->student_id)->first();
        $students->user_id = $user->id;
        if($students->save()){
            $msg = "অভিনন্দন! $username->sname, আপনার ওয়েব পোর্টাল লিংকঃ https://innovacomputersbd.com/user/login । ইউজার আইডিঃ $request->email পাসওয়ার্ডঃ $request->password । ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।";
            $this->send_sms($username->phone,$msg);
            return redirect()->route('admin.users.index')->with('success','User created successfully');
        }
        else{
            return $this->responseRedirectBack('Error occurred while creating User.', 'error', true, true);
        }
        
    }


    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $user = User::find($id);
        $this->setPageTitle('User Management', 'User '.$user->name);
        return view('admin.users.show',compact('user'));
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user = User::find($id);
        $student = Students::where('deleted_at', null)->get();
        // $userRole = $user->roles->pluck('name','name')->all();

        $this->setPageTitle('Edit User', 'Edit '.$user->name);
        return view('admin.users.edit',compact('user','student'));
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            // 'name' => 'required',
            'email' => 'required|email|unique:users,email,'.$id,
            'password' => 'same:confirm-password',
            'student_id' => 'required'
        ]);

        $input = $request->all();
        $username = Students::where('id', $request->student_id)->first();
        $input['name'] = $username->sname;
        if(!empty($input['password'])){ 
            $input['password'] = Hash::make($input['password']);
        }else{
            $input = array_except($input,array('password'));    
        }

        $user = User::find($id);
        $user->update($input);
        // DB::table('model_has_roles')->where('model_id',$id)->delete();
        // $user->assignRole($request->input('roles'));
        // $students = Students::where('user_id', $request->id)->first();
        // if($students->user_id != $request->student_id){
        //     $students->user_id = null;
        //     $NewStudents = Students::where('id', $request->student_id)->first();
        //     $NewStudents->user_id = $request->student_id;
        //     $NewStudents->save();
        // }

        return redirect()->route('admin.users.index')->with('success','User updated successfully');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        User::find($id)->delete();
        $students = Students::where('user_id',$id)->first();
        $students->user_id = NULL;
        $students->save();
        return redirect()->route('admin.users.index')->with('success','User deleted successfully');
    }

    public function send_sms($phone,$msg){
        return app(\App\Services\SmsService::class)->sendRaw($phone, $msg);
    }
}