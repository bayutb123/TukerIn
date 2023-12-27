<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\CreateChatRequest;
use App\Http\Requests\SendMessageRequest;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use App\Models\MessageImage;

class ChatController extends Controller
{
    protected $user;
    protected $chat;
    protected $message;

    public function __construct(User $user, Chat $chat, Message $message)
    {
        $this->user = $user;
        $this->chat = $chat;
        $this->message = $message;
    }

    public function getChats($userId)
    {
        $chats = $this->chat->where('user_id', $userId)->orWhere('receiver_id', $userId)->get();
        foreach ($chats as $chat) {
            $chat->receiver = $this->user->find($chat->receiver_id);
            $chat->last_message = $this->message->where('chat_id', $chat->id)->orderBy('created_at', 'desc')->first();
        }

        return response()->json([
            'message' => 'Chats retrieved successfully',
            'data' => $chats,
        ]);
    }

    public function getMessages($chatId)
    {
        $messages = $this->message->where('chat_id', $chatId)->get();

        $messages->map(function ($message) {
            $message->attachments = MessageImage::where('message_id', $message->id)->get();
        });

        return response()->json([
            'message' => 'Messages retrieved successfully',
            'data' => $messages,
        ]);
    }

    public function createChat(CreateChatRequest $request)
    {

        $chat = $this->chat->create($request->all());

        return response()->json([
            'message' => 'Chat created successfully',
            'data' => $chat,
        ], 201);
    }

    public function sendMessage(SendMessageRequest $request)
    {
        $validated = $request->validated();

        $message = $this->message->create($request->all());

        $uploaded = [];
        if ($validated) {
            if (is_array($request->image)) {
                $arrayIndex = 0;
                foreach ($request->image as $image) {
                    $imageName = $request['post_id'] . time() . '_' . $arrayIndex++ . '.' . $image->extension();
                    $image->move(public_path('images/message'), $imageName);
                    $image = MessageImage::create([
                        'message_id' => $message->id,
                        'image' => $imageName
                    ]);
                    array_push($uploaded, $image);
                }
            }

            return response()->json([
                'message' => 'Message sent successfully',
                'data' => $message,
            ], 201);
        }

        
    }

    public function readMessage($chatId, $userId)
    {
        $messages = $this->message->where('chat_id', $chatId)->where('receiver_id', $userId)->get();

        foreach ($messages as $message) {
            if ($message->is_read == 0) {
                $message->is_read = 1;
                $message->save();
            }
        }

        return response()->json([
            'message' => 'Message read successfully',
        ], 200);
    }
}
