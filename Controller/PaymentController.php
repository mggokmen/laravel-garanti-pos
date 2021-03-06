<?php

namespace App\Http\Controllers;


class PaymentController extends Controller
{

    public function index(Request $request)
    {

        if(auth()->user()){
            $carts=Carts::where('user_id',auth()->user()->id)->where('status','continues')->first();
            $address=Addresses::where('id',auth()->user()->default_address)->first();

            if(!$address){
                $request->session()->flash('error', trans('payment.empty.address.error'));
                return redirect(route('account'));
            }
        }else{
            return redirect(route('home'));
        }
        
        return view('payment',['carts'=>$carts,'address'=>$address]);

    }

    public function garanti(Request $request)
    {

        if(auth()->user()){
            $carts=Carts::where('user_id',auth()->user()->id)
                ->where('status','continues')
                ->first();
        }else{
            return redirect(route('home'));
        }

        $orderId=date('Ymd').substr("00000000".$carts->id,-8).date('Hi');

        $order=Orders::where('order_id',$orderId)->first();
        if(!$order){
            $order=new Orders;
        }

        $oldOrders=Orders::whereNotIn('order_id',[$orderId])
            ->where('cart_id',$carts->id)
            ->where('status','pending')
            ->get();

        if($oldOrders){
            foreach($oldOrders as $oldOrder){
                $oldOrder->status="cancelled";
                $oldOrder->result_message="OLDORDER|".$oldOrder->result_message;
                $oldOrder->touch();
            }
        }

        $order->order_id=$orderId;
        $order->cart_id=$carts->id;
        $order->cart_price=$carts->price;
        $order->courier_fee=config('settings')['fee.courier'];
        $order->bag_fee=config('settings')['fee.bag'];
        $order->total_price=($order->cart_price)+($order->courier_fee)+($order->bag_fee);
        $order->customer_note=$request->input('customer_note');
        $order->status='pending';
        $order->created_at=date('Y-m-d H:i:s');
        $order->save();
        
        return view('garanti.pay',['orderId'=>$orderId]);

    }
    
    public function garantiResult(Request $request)
    {


        if($request->input('orderId')==""){
            $request->session()->flash('paymentError', trans('payment.orderId.failed'));
            return redirect(route('home'));
        }

        if(auth()->user()){
            $carts=Carts::where('user_id',auth()->user()->id)
                ->where('status','continues')
                ->first();
        }else{
            return redirect(route('home'));
        }

        $orderId=$request->input('orderId');
        $order=Orders::where('order_id',$orderId)->first();
        if(!$order){
            $request->session()->flash('paymentError', trans('payment.orderId.failed'));
            return redirect(route('home'));  
        }

        $totalPrice=$order->total_price*100;

        $params = array(

            // M????teri tan??mlar??
            'orderNo' => $orderId, // Sipari?? numaras??
            'amount' => $totalPrice, // ??ekilecek tutar (ondal??kl?? olarak de??il tam say?? olarak g??nderilmeli, ??rn. 1.20tl i??in 120 g??nderilmeli)
            'customerEmail' => '####', // M????teri e-mail adresi
            'customerIP' => getenv('REMOTE_ADDR'), // M????teri ip adresi
            'successUrl' => route('garanti.successs'), // Olumlu sonu?? url
            'errorUrl' => route('garanti.errors'), // Olumsuz sonu?? url
        
            // Kart bilgisi tan??mlar?? (GarantiPay ile ??demede bu alanlar??n doldurulmas?? zorunlu de??ildir)
            'cardName' => $request->input('cardName'), // Kart ??zerindeki ad soyad
            'cardNumber' => $request->input('cardNumber'), // Kart numaras?? (16 haneli bo??luksuz)
            'cardExpiredMonth' => $request->input('cardExpiredMonth'), // Kart ge??erlilik tarihi ay
            'cardExpiredYear' => $request->input('cardExpiredYear'), // Kart ge??erlilik tarihi y??l (y??l??n son 2 hanesi)
            'cardCvv' => $request->input('cardCvc') // Kart??n arka y??z??ndeki son 3 numara(CVV kodu)
        );

        $garantipos = new Garanti();
        $garantipos->debugMode = false;
        $garantipos->setParams($params);

        $garantipos->debugUrlUse = false; // Parametre de??erlerinin check edildi??i adrese g??nderilmesi
        $garantipos->pay(); // 3D do??rulama i??in bankaya y??nlendiriliyor

        return view('garanti.result',['carts'=>'']);

    }

    public function garantiSuccess(Request $request)
    {

        $garantipos=new Garanti();
        $post = $request->post();
        $result=$garantipos->callback($post,"success");

        $alert="success";
        if($result['status']!="success") $alert="danger";

        return view('garanti.success',[
            'status'=>$result['status'],
            'alert'=>$alert,
            'message'=>$result['message']
        ]);

    }

    public function garantiError(Request $request)
    {

        $garantipos=new Garanti();
        $post = $request->post();
        $result = $garantipos->callback($post,"error");

        return view('garanti.error',[
            'status'=>$result['status'],
            'message'=>$result['message']
        ]);
    }


}
