<?php

namespace App\Http\Controllers\Admin; 

use App\Models\FeesTransections;
use App\Models\Students;
use App\Models\Courses;
use App\Models\Batchs;
use App\Contracts\FeesTransactionsContract;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use DB;
use PDF;
use SimpleSoftwareIO\QrCode\Facades\QrCode;


class FeesTransectionsController extends BaseController
{
    /**
     * @var FeesTransactionsContract
     */
    protected $feesRepository;
    /**
     * CategoryController constructor.
     * @param FeesTransactionsContract $categoryRepository
     */
    public function __construct(FeesTransactionsContract $feesRepository)
    {
        $this->feesRepository = $feesRepository;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $fee = $this->feesRepository->listFeesTransactions();
        // $fee = DB::table('batchs')->where('deleted_at', null)->get();
        $fee = FeesTransections::orderBy('id', 'desc')->get();
        $this->setPageTitle('Fees Record', 'List of all Fees');
        return view('admin.payments.fees', compact('fee'));
     
    }
    

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($id)
    {
        $fees = $this->feesRepository->listFeesTransactions('id', 'asc');
        // $batchs = DB::table('batchs')->where('deleted_at', null)->get();
        // $courses = DB::table('courses')->where('deleted_at', null)->get();
        $student = DB::table('students')->where('id',$id)->first();
        //dd($student);
        $this->setPageTitle('Generate Student Payment', 'Payments of '.$student->sname);
        return view('admin.payments.create', compact('fees', 'student'));
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
            'paidamount'      =>  'required',
            'paymentType' =>  'required',
            'paiddate' =>  'required',
            'paidremark'     =>  'required'
        ]);
        //  dd($request->all());

        $params = $request->except('_token');

        $fees = $this->feesRepository->createFeesTransactions($params);
        $students = Students::where('id', $request->student_id)->first();
        $paid = number_format($request->paidamount, 2);
        $pay = FeesTransections::where('student_id', $request->student_id)->where('paymentType', 1)->sum('paidamount');
        $due = $students['balance'] - $pay;
        $finalDue = number_format($due,2);

                                            
        if (!$fees) {
            return $this->responseRedirectBack('Error occurred while creating Payments.', 'error', true, true);
        }
        else{
            if($request->paymentType == 1){
                $msg = "প্রিয়  $students[sname], আপনি কোর্স ফিঃ $paid  টাকা জমা দিয়েছেন। আপনার বর্তমান বকেয়া $finalDue টাকা। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।";
                $this->send_sms($students['phone'],$msg);
            }
            else{
                $msg =  "প্রিয় $students[sname], আপনি রেজিস্ট্রেশন ফিঃ  $paid টাকা জমা দিয়েছেন। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।";
                $this->send_sms($students['phone'],$msg);
            }
            return $this->responseRedirect('admin.fees.index', 'Payments added successfully' ,'success',false, false);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\FeesTransections  $feesTransections
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $students = Students::where('id',$id)->first();
        $fees = FeesTransections::where('student_id',$id)->where('paymentType',1)->get();
        $paid = FeesTransections::where('student_id',$id)->where('paymentType',1)->sum('paidamount');
        //$regfeespaid = FeesTransections::where('student_id',$id)->where('paymentType',2)->sum('paidamount');
        $paid = $paid;
        $this->setPageTitle('Payments Details', 'Payments Details');
        return view('admin.payments.show', compact('students', 'fees', 'paid'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\FeesTransections  $feesTransections
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $targetFees = $this->feesRepository->findFeesTransactionsById($id);

        $this->setPageTitle('Transaction', 'View Transaction');
        return view('admin.payments.edit', compact('targetFees'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FeesTransections  $feesTransections
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $this->validate($request, [
            'paidamount'      =>  'required',
            'paymentType' =>  'required',
            'paiddate' =>  'required',
            'paidremark'     =>  'required'
        ]);
        $params = $request->except('_token');

        $payments = $this->feesRepository->updateFeesTransactions($params);

        if (!$payments) {
            return $this->responseRedirectBack('Error occurred while updating Transaction.', 'error', true, true);
        }
        return $this->responseRedirect('admin.fees.feelog', 'Transaction updated successfully' ,'success',false, false);
    }

    public function FessStatus(Request $request, $id){ 
        if($request->status == 0){
            $students = Students::where('id', $request->student_id)->first();
            $paid = number_format($request->paidamount, 2);
            $pay = FeesTransections::where('student_id', $students->id)->where('paymentType', 1)->sum('paidamount');
            $due = $students->balance - $pay;
            $finalDue = number_format($due,2);
            $data = FeesTransections::find($id);
            $data->status = $request->status;
            $data->update();
            if($request->paymentType == 1 ){
                $msg = "প্রিয়  $students[sname], আপনি কোর্স ফিঃ $paid  টাকা জমা দিয়েছেন। আপনার বর্তমান বকেয়া $finalDue টাকা। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।";
                $this->send_sms($students['phone'],$msg);
            }
            else{
                $msg =  "প্রিয় $students[sname], আপনি রেজিস্ট্রেশন ফিঃ  $paid টাকা জমা দিয়েছেন। ধন্যবাদ, ইনোভা কম্পিউটার্স বিডি।";
                $this->send_sms($students['phone'],$msg);
            }
            return $this->responseRedirect('admin.fees.feelog', 'Payments has been approved' ,'success',false, false);
        }
        else{
            $data = FeesTransections::find($id);
            $data->status = $request->status;
            $data->update();
            return $this->responseRedirect('admin.fees.feelog', 'Payments has been Pending !!' ,'error',true, true);
         }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\FeesTransections  $feesTransections
     * @return \Illuminate\Http\Response
     */
    public function destroy(FeesTransections $feesTransections)
    {
        //
    }
    
    /**
     * Show payslip view (print preview)
     */
    public function payslip($id)
    {
        $transaction = FeesTransections::findOrFail($id);
        $student = Students::where('id', $transaction->student_id)->first();
        $this->setPageTitle('Payslip', 'Payslip Preview');
        // generate server-side PNG QR and embed as data URI (offline-safe)
        try {
            $qrText = "Payslip ID: {$transaction->id}\nStudent: {$student->sname}\nAmount: {$transaction->paidamount}\nDate: {" . ($transaction->paiddate ?? $transaction->created_at) . "}";
            $png = QrCode::format('png')->size(300)->generate($qrText);
            $qrDataUri = 'data:image/png;base64,' . base64_encode($png);
        } catch (\Exception $e) {
            $qrDataUri = null;
        }

        // safe amount-in-words fallback to avoid undefined variable in views
        $amountNumeric = floatval($transaction->paidamount ?? 0);
        $amountInWords = number_format($amountNumeric, 2) . ' Taka only';

        // compute totals for student using Payments Details logic
        // totalCourseFee = students->course_fee
        // totalDiscount = course_fee - balance
        // totalPaid = sum paidamount where paymentType == 1
        // netDue = balance - totalPaid
        $studentId = $student->id ?? null;
        $totalCourseFee = 0;
        $totalDiscount = 0;
        $totalPaid = 0;
        if ($studentId) {
            $totalCourseFee = floatval($student->course_fee ?? 0);
            $totalDiscount = floatval((($student->course_fee ?? 0) - ($student->balance ?? 0)));
            $totalPaid = floatval(FeesTransections::where('student_id', $studentId)->where('paymentType',1)->sum('paidamount')) ?: 0;
        }

        $netDue = floatval(($student->balance ?? 0) - $totalPaid);

        return view('admin.payments.payslip', compact('transaction', 'student', 'qrDataUri', 'amountInWords', 'totalCourseFee', 'totalDiscount', 'totalPaid', 'netDue'));
    }

    /**
     * Download payslip as PDF
     */
    public function downloadPayslip($id)
    {
        $transaction = FeesTransections::findOrFail($id);
        $student = Students::where('id', $transaction->student_id)->first();

        // generate server-side PNG QR and embed as data URI for PDF
        try {
            $qrText = "Payslip ID: {$transaction->id}\nStudent: {$student->sname}\nAmount: {$transaction->paidamount}\nDate: {" . ($transaction->paiddate ?? $transaction->created_at) . "}";
            $png = QrCode::format('png')->size(300)->generate($qrText);
            $qrDataUri = 'data:image/png;base64,' . base64_encode($png);
        } catch (\Exception $e) {
            $qrDataUri = null;
        }

        // safe amount-in-words fallback to avoid undefined variable in views
        $amountNumeric = floatval($transaction->paidamount ?? 0);
        $amountInWords = number_format($amountNumeric, 2) . ' Taka only';

        // compute totals for student for PDF (same logic as payslip view)
        $studentId = $student->id ?? null;
        $totalCourseFee = 0;
        $totalDiscount = 0;
        $totalPaid = 0;
        if ($studentId) {
            $totalCourseFee = floatval($student->course_fee ?? 0);
            $totalDiscount = floatval((($student->course_fee ?? 0) - ($student->balance ?? 0)));
            $totalPaid = floatval(FeesTransections::where('student_id', $studentId)->where('paymentType',1)->sum('paidamount')) ?: 0;
        }

        $netDue = floatval(($student->balance ?? 0) - $totalPaid);

        $pdf = PDF::loadView('admin.payments.payslip', compact('transaction','student','qrDataUri','amountInWords','totalCourseFee','totalDiscount','totalPaid','netDue'));
        // ensure A4 paper size
        $pdf->setPaper('A4', 'portrait');
        $filename = 'payslip_'.$transaction->id.'.pdf';
        return $pdf->download($filename);
    }
    
    public function send_sms($phone,$msg){
        return app(\App\Services\SmsService::class)->sendRaw($phone, $msg);
    }
}
