<?php

namespace App\Http\Controllers;

use App\Room;
use App\User;
use App\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Notifications\CustomerWaitingPaidNotification;
use App\Notifications\CustomerPaidNotification;
use Illuminate\Support\Facades\Notification;

class BookingController extends Controller
{
    //get all bookings
    public function index()
    {
        if (Auth::user()->is_admin) {
            $data['success'] = true;
            $data['bookings'] =  Booking::join('rooms', 'rooms.id', '=', 'bookings.room_id')
                ->join('hotels', 'hotels.id', '=', 'rooms.hotel_id')
                ->join('users', 'users.id', '=', 'bookings.user_id')
                ->select('bookings.id', 'hotels.name', 'users.first_name', 'users.last_name', 'hotels.city', 'hotels.image', 'rooms.price', 'bookings.check_in', 'bookings.check_out', 'bookings.created_at')
                ->groupBy('bookings.id', 'hotels.name', 'users.first_name', 'users.last_name', 'hotels.city', 'hotels.image', 'rooms.price', 'bookings.check_in', 'bookings.check_out', 'bookings.created_at')
                ->orderBy('bookings.created_at', 'desc')
                ->paginate(6);
        } else
            $data['success'] = false;


        return response()->json(['data' => $data]);
    }

    // get all bookings with status payment
    public function getAllPaymentBooking()
    {
        if (Auth::user()->is_admin) {
            $data['success'] = true;
            $data['bookings'] =  Booking::join('rooms', 'rooms.id', '=', 'bookings.room_id')
            ->join('hotels', 'hotels.id', '=', 'rooms.hotel_id')
            ->join('users', 'users.id', '=', 'bookings.user_id')
            ->select(
                'bookings.id',
                'hotels.name',
                'users.first_name',
                'users.last_name',
                'hotels.city',
                'hotels.image as hotel_image',
                'rooms.price',
                'bookings.check_in',
                'bookings.check_out',
                'bookings.email',
                'bookings.total_price',
                'bookings.image',
                'bookings.status',
                'bookings.created_at'
            )
            ->groupBy(
                'bookings.id',
                'hotels.name',
                'users.first_name',
                'users.last_name',
                'hotels.city',
                'hotel_image',
                'rooms.price',
                'bookings.check_in',
                'bookings.check_out',
                'bookings.email',
                'bookings.total_price',
                'bookings.image',
                'bookings.status',
                'bookings.created_at'
            )
            ->orderBy('bookings.created_at', 'desc')
            ->paginate(6);
        } else {
            $data['success'] = false;
        }
        
        return response()->json(['data' => $data, 'success' => true,]);
    }

    //create a booking
    public function store(Request $request)
    {
        try {
            $data = $this->validateData([
                'check_in' => 'required',
                'check_out' => 'required',
                'user_id' => '',
                'room_id' => 'required',
                'full_name' => 'required',
                'email' => 'required',
                'telephone' => 'required',
                'image' => 'required',
            ]);
    
            $user_id = $request->filled('user_id') ? $request->user_id : null;
            
                $fileName = null;
                if ($request->file('image')) {
                    $fileName = "booking_" . time() . "." . $request->file('image')->getClientOriginalExtension();
                    $request->file('image')->move(public_path("/img/booking"), $fileName);
                }
    
                $booking = new Booking;
                $booking->check_in = $request->check_in;
                $booking->check_out = $request->check_out;
                $booking->user_id = $user_id;
                $booking->room_id = $request->room_id;
                $booking->full_name = $request->full_name;
                $booking->email = $request->email;
                $booking->telephone = $request->telephone;
                $booking->image = $fileName;
                $booking->total_price = $request->total_price;
    
                $notification = new CustomerWaitingPaidNotification([
                    'full_name' => $request->full_name,
                    'check_in' => $request->check_in,
                    'check_out' => $request->check_out,
                ]);

                if ($booking->save()) {
                    try {
                        Notification::route('mail', $request->email)->notify($notification);
                        // Notification::route('mail', $request->email)->notify(new CustomerWaitingPaidNotification($data));
                    } catch (\Exception $e) {
                        return response()->json(['error' => 'Pengiriman notifikasi gagal']);
                        $data['error_message'] = $th->getMessage();
                    }

                    $data['success'] = true;
                    $data['booking'] = $booking;
                }
            
        } catch (\Throwable $th) {
            $data['success'] =  false;
            $data['error_message'] = $th->getMessage();
            return response()->json(['error' => 'Pengiriman notifikasi gagal']);
        }
        return response()->json(['data' => $data]);
    }

    public function updateStatus(Request $request, Booking $booking, $id){
        try {
            if (Auth::user()->is_admin) {
                $booking = Booking::findOrFail($id);
                $booking->status = $request->status;

                $data = [
                    'booking_id' => $booking->id,
                    'status' => $request->status
                ];

                
                
                if ($booking->save()) {
                    try {
                        Notification::route('mail', $request->email)->notify(new CustomerPaidNotification($data));
                    } catch (\Exception $e) {
                        return response()->json(['error' => 'Pengiriman notifikasi gagal']);
                        $data['error_message'] = $th->getMessage();
                    }
                    return response()->json(['success' => true, 'booking' => $booking]);
                } else {
                    return response()->json(['success' => false, 'error' => 'Gagal mengupdate status booking']);
                }
            } else {
                $data['success'] = false;
            }
        } catch (\Throwable $th) {
            return response()->json(['success' => false, 'error' => $th->getMessage()]);
        }
    }

    //get the bookings of a user
    public function getUserBookings(User $user)
    {
        if ($user->id == Auth::user()->id) {
            $data['success'] = true;
            $data['bookings'] =  Booking::join('rooms', 'rooms.id', '=', 'bookings.room_id')
                ->join('hotels', 'hotels.id', '=', 'rooms.hotel_id')
                ->select('bookings.id', 'bookings.check_in', 'bookings.check_out', 'hotels.name', 'hotels.city', 'hotels.image', 'rooms.price', 'bookings.created_at')
                ->groupBy('bookings.id', 'bookings.check_in', 'bookings.check_out', 'hotels.name', 'hotels.city', 'hotels.image', 'rooms.price', 'bookings.created_at')
                ->orderBy('bookings.created_at', 'desc')
                ->where('bookings.user_id', $user->id)
                ->paginate(6);
        } else
            $data['success'] = false;

        return response()->json(['data' => $data]);
    }

    // get the payment status bookings of a user
    public function getUserBookingsPaymentStatus(User $user)
    {
        if ($user->id == Auth::user()->id) {
            $data['success'] = true;
            $data['bookings'] =  Booking::join('rooms', 'rooms.id', '=', 'bookings.room_id')
            ->join('hotels', 'hotels.id', '=', 'rooms.hotel_id')
            ->select(
                'bookings.id',
                'bookings.check_in',
                'bookings.check_out',
                'hotels.name',
                'hotels.city',
                'hotels.image as hotel_image',
                'rooms.price',
                'bookings.total_price',
                'bookings.image',
                'bookings.status',
                'bookings.created_at'
            )
            ->groupBy(
                'bookings.id',
                'bookings.check_in',
                'bookings.check_out',
                'hotels.name',
                'hotels.city',
                'hotel_image',
                'rooms.price',
                'bookings.total_price',
                'bookings.image',
                'bookings.status',
                'bookings.created_at'
            )
            ->orderBy('bookings.created_at', 'desc')
            ->where('bookings.user_id', $user->id)
            ->paginate(6);
        } else {
            $data['success'] = false;
        }
        
        return response()->json(['data' => $data]);
    }

    //Validate data and return data with errors if exist
    public function validateData(array $rules)
    {
        $validator = Validator::make(request()->all(), $rules);
        if ($validator->fails()) {

            $data['errors'] =  $validator->errors();

            return $data;
        }
        return null;
    }
}
