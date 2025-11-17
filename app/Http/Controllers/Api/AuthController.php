<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Account;
use App\Models\Balance;
use App\Models\DailyLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Digital Wallet API',
    description: 'API REST para gerenciamento de carteira digital com autenticação JWT'
)]
#[OA\Server(url: 'http://localhost:8000', description: 'Local Server')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
class AuthController extends Controller
{
    #[OA\Post(
        path: '/api/auth/register',
        tags: ['Authentication'],
        summary: 'Register a new user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        required: ['name', 'email', 'password', 'password_confirmation'],
                        properties: [
                            new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                            new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                            new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123')
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                                new OA\Property(property: 'account_number', type: 'string', example: 'DW12345678'),
                                new OA\Property(property: 'token', type: 'string'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            // Generate account number and details
            $accountNumber = 'DW' . str_pad((string) rand(1, 99999999), 8, '0', STR_PAD_LEFT);
            $agency = str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $accountNum = str_pad((string) rand(1, 999999999), 9, '0', STR_PAD_LEFT);
            $digit = rand(0, 9);

            // Create account
            $account = Account::create([
                'user_id' => $user->id,
                'agency' => $agency,
                'account' => $accountNum,
                'account_digit' => (string) $digit,
                'account_number' => $accountNumber,
                'account_type' => 'digital_wallet',
                'status' => 'active',
            ]);

            // Create initial balance
            Balance::create([
                'account_id' => $account->id,
                'amount' => 0.00,
            ]);

            // Create daily limits
            $limits = [
                ['account_id' => $account->id, 'limit_type' => 'deposit', 'daily_limit' => 10000.00, 'current_used' => 0, 'reset_at' => now()->toDateString()],
                ['account_id' => $account->id, 'limit_type' => 'withdraw', 'daily_limit' => 5000.00, 'current_used' => 0, 'reset_at' => now()->toDateString()],
                ['account_id' => $account->id, 'limit_type' => 'transfer', 'daily_limit' => 5000.00, 'current_used' => 0, 'reset_at' => now()->toDateString()],
            ];
            DailyLimit::insert($limits);

            DB::commit();

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'data' => [
                    'user' => new UserResource($user),
                    'account_number' => $accountNumber,
                    'token' => $token,
                    'token_type' => 'bearer',
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'data' => [
                    'error' => 'Operação não pode ser realizada, tente novamente. Se o erro persistir, entre em contato com nosso suporte.',
                    'code' => 'REGISTRATION_ERROR',
                ],
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Authentication'],
        summary: 'Login user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['data'],
                properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'object',
                        required: ['email', 'password'],
                        properties: [
                            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'test@example.com'),
                            new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password')
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                                new OA\Property(property: 'token', type: 'string'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials')
        ]
    )]
    /**
     * Login user
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'data' => [
                    'error' => 'Credenciais inválidas',
                    'code' => 'INVALID_CREDENTIALS',
                ],
            ], 401);
        }

        $user = auth()->user();

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'bearer',
            ],
        ]);
    }

    /**
     * Logout user (invalidate token)
     */
    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json([
            'data' => [
                'message' => 'Logout realizado com sucesso',
            ],
        ]);
    }

    /**
     * Refresh token
     */
    public function refresh(): JsonResponse
    {
        $token = JWTAuth::refresh(JWTAuth::getToken());

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'bearer',
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/auth/me',
        tags: ['Authentication'],
        summary: 'Get authenticated user with account details',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
                                new OA\Property(
                                    property: 'account',
                                    properties: [
                                        new OA\Property(property: 'agency', type: 'string', example: '0001'),
                                        new OA\Property(property: 'account', type: 'string', example: '123456789'),
                                        new OA\Property(property: 'account_digit', type: 'string', example: '7'),
                                        new OA\Property(property: 'account_number', type: 'string', example: 'DW12345678'),
                                        new OA\Property(property: 'account_type', type: 'string', example: 'digital_wallet'),
                                        new OA\Property(property: 'status', type: 'string', example: 'active'),
                                        new OA\Property(property: 'balance', type: 'number', format: 'float', example: 1000.00)
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    /**
     * Get authenticated user with account details
     */
    public function me(): JsonResponse
    {
        $user = auth()->user();
        $user->load(['account.balance']);

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
