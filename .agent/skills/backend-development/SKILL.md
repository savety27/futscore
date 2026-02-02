---
name: backend-development
description: Build robust, secure, and scalable backend systems with production-grade code quality. Use this skill when developing APIs, database schemas, server-side logic, authentication systems, or any backend infrastructure. Covers PHP, Node.js, Python, and other backend technologies with emphasis on security, performance, and maintainability.
license: MIT
version: "1.0.0"
---

This skill guides the creation of production-ready backend systems that prioritize security, performance, scalability, and maintainability. Implement real working code with exceptional attention to architecture, data integrity, and best practices.

The user provides backend requirements: an API endpoint, database schema, authentication system, business logic, or complete backend application. They may include context about the tech stack, scale requirements, or specific constraints.

## Backend Development Principles

Before coding, understand the context and commit to a solid architectural approach:

- **Purpose**: What business problem does this solve? What are the data flows?
- **Scale**: How many users? What's the expected load? Growth trajectory?
- **Security**: What data needs protection? What are the threat vectors?
- **Tech Stack**: Language/framework constraints, existing infrastructure, team expertise
- **Integration**: What external services, databases, or APIs are involved?

**CRITICAL**: Design for the actual requirements, not hypothetical scale. Over-engineering wastes time; under-engineering creates technical debt. Find the right balance.

## Core Backend Guidelines

### 1. Database Design & Data Integrity

**Schema Design:**
- Use proper normalization (typically 3NF) unless denormalization is justified for performance
- Define clear primary keys (prefer auto-increment integers or UUIDs based on use case)
- Establish foreign key constraints to maintain referential integrity
- Add appropriate indexes on frequently queried columns
- Use meaningful, consistent naming conventions (snake_case for SQL databases)
- Include audit fields: `created_at`, `updated_at`, `deleted_at` (for soft deletes)

**Data Types:**
- Choose appropriate data types (don't use VARCHAR(255) for everything)
- Use ENUM or lookup tables for fixed sets of values
- Store monetary values in DECIMAL, not FLOAT
- Use proper date/time types (DATETIME, TIMESTAMP) with timezone awareness
- Consider JSON columns for flexible, non-relational data (when appropriate)

**Migrations:**
- Always use database migrations for schema changes
- Make migrations reversible when possible
- Never modify existing migrations that have been deployed
- Include data migrations separately from schema migrations

**Example Schema (MySQL):**
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'coach', 'player') NOT NULL DEFAULT 'player',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    email_verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Security Best Practices

**Authentication & Authorization:**
- NEVER store passwords in plain text - use bcrypt, Argon2, or PBKDF2
- Implement proper session management with secure, httpOnly cookies
- Use JWT tokens correctly (short expiration, refresh tokens, proper signing)
- Implement role-based access control (RBAC) or attribute-based access control (ABAC)
- Add rate limiting to prevent brute force attacks
- Implement account lockout after failed login attempts

**Input Validation & Sanitization:**
- ALWAYS validate and sanitize user input
- Use parameterized queries or prepared statements to prevent SQL injection
- Validate data types, lengths, formats, and ranges
- Sanitize output to prevent XSS attacks
- Use whitelisting over blacklisting for validation

**Data Protection:**
- Encrypt sensitive data at rest (PII, payment info, etc.)
- Use HTTPS/TLS for all data in transit
- Implement proper CORS policies
- Set secure headers (CSP, X-Frame-Options, etc.)
- Never expose sensitive information in error messages
- Implement proper logging (but don't log sensitive data)

**Example: Secure Password Hashing (PHP):**
```php
// Hashing password
$password_hash = password_hash($password, PASSWORD_ARGON2ID);

// Verifying password
if (password_verify($input_password, $stored_hash)) {
    // Password is correct
    
    // Check if rehashing is needed (algorithm updated)
    if (password_needs_rehash($stored_hash, PASSWORD_ARGON2ID)) {
        $new_hash = password_hash($input_password, PASSWORD_ARGON2ID);
        // Update database with new hash
    }
}
```

**Example: SQL Injection Prevention (PHP with PDO):**
```php
// WRONG - Vulnerable to SQL injection
$query = "SELECT * FROM users WHERE email = '$email'";

// CORRECT - Using prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();
```

### 3. API Design

**RESTful Principles:**
- Use proper HTTP methods (GET, POST, PUT, PATCH, DELETE)
- Design resource-oriented URLs (`/api/users/{id}`, not `/api/getUser`)
- Return appropriate HTTP status codes (200, 201, 400, 401, 403, 404, 500, etc.)
- Use consistent response formats (JSON is standard)
- Implement proper error handling with meaningful error messages
- Version your API (`/api/v1/users`)

**Request/Response Structure:**
```json
// Success Response
{
    "success": true,
    "data": {
        "id": 123,
        "name": "John Doe",
        "email": "john@example.com"
    },
    "meta": {
        "timestamp": "2026-02-02T20:50:00Z"
    }
}

// Error Response
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Invalid input data",
        "details": [
            {
                "field": "email",
                "message": "Email format is invalid"
            }
        ]
    },
    "meta": {
        "timestamp": "2026-02-02T20:50:00Z"
    }
}
```

**Pagination:**
```json
// Request: GET /api/users?page=2&limit=20

// Response
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 2,
        "per_page": 20,
        "total": 150,
        "total_pages": 8,
        "has_next": true,
        "has_prev": true
    }
}
```

### 4. Error Handling & Logging

**Error Handling:**
- Use try-catch blocks for exception handling
- Create custom exception classes for different error types
- Never expose stack traces or internal errors to users
- Log errors with context (user ID, request ID, timestamp)
- Implement global error handlers
- Return user-friendly error messages

**Logging:**
- Use proper log levels (DEBUG, INFO, WARNING, ERROR, CRITICAL)
- Include context: timestamp, user ID, request ID, IP address
- Don't log sensitive data (passwords, tokens, credit cards)
- Use structured logging (JSON format) for easier parsing
- Implement log rotation to manage disk space
- Consider centralized logging for distributed systems

**Example: Error Handling (PHP):**
```php
class ValidationException extends Exception {}
class DatabaseException extends Exception {}
class AuthenticationException extends Exception {}

try {
    // Business logic
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new ValidationException("Invalid email format");
    }
    
    // Database operation
    $result = $db->query($sql);
    if (!$result) {
        throw new DatabaseException("Database query failed");
    }
    
} catch (ValidationException $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => $e->getMessage()
        ]
    ]);
    error_log("Validation error: " . $e->getMessage());
    
} catch (DatabaseException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'SERVER_ERROR',
            'message' => 'An error occurred. Please try again later.'
        ]
    ]);
    error_log("Database error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'An unexpected error occurred.'
        ]
    ]);
    error_log("Unexpected error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
}
```

### 5. Code Organization & Architecture

**Project Structure:**
- Separate concerns: controllers, models, services, repositories
- Use dependency injection for better testability
- Follow SOLID principles
- Implement design patterns appropriately (Repository, Factory, Strategy, etc.)
- Keep business logic separate from framework code
- Use environment variables for configuration

**Example Structure (PHP MVC):**
```
project/
├── config/
│   ├── database.php
│   └── app.php
├── src/
│   ├── Controllers/
│   │   ├── UserController.php
│   │   └── AuthController.php
│   ├── Models/
│   │   └── User.php
│   ├── Services/
│   │   ├── AuthService.php
│   │   └── EmailService.php
│   ├── Repositories/
│   │   └── UserRepository.php
│   ├── Middleware/
│   │   ├── AuthMiddleware.php
│   │   └── RateLimitMiddleware.php
│   └── Validators/
│       └── UserValidator.php
├── public/
│   └── index.php
├── tests/
├── vendor/
└── .env
```

**Example: Service Layer (PHP):**
```php
class UserService {
    private $userRepository;
    private $emailService;
    
    public function __construct(UserRepository $userRepo, EmailService $emailService) {
        $this->userRepository = $userRepo;
        $this->emailService = $emailService;
    }
    
    public function registerUser(array $data): User {
        // Validate input
        $validator = new UserValidator();
        $validator->validateRegistration($data);
        
        // Check if user exists
        if ($this->userRepository->findByEmail($data['email'])) {
            throw new ValidationException("Email already registered");
        }
        
        // Hash password
        $data['password_hash'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        unset($data['password']);
        
        // Create user
        $user = $this->userRepository->create($data);
        
        // Send welcome email
        $this->emailService->sendWelcomeEmail($user);
        
        return $user;
    }
}
```

### 6. Performance Optimization

**Database Optimization:**
- Use indexes strategically (but don't over-index)
- Optimize queries (avoid N+1 problems, use JOINs wisely)
- Implement query caching where appropriate
- Use database connection pooling
- Consider read replicas for read-heavy applications
- Monitor slow queries and optimize them

**Caching:**
- Cache frequently accessed data (Redis, Memcached)
- Implement cache invalidation strategies
- Use HTTP caching headers (ETag, Cache-Control)
- Cache at multiple levels (database, application, CDN)

**Code Optimization:**
- Avoid premature optimization (profile first)
- Use lazy loading for heavy operations
- Implement pagination for large datasets
- Use asynchronous processing for long-running tasks (queues)
- Optimize file uploads (chunking, compression)

**Example: Caching (PHP with Redis):**
```php
class CacheService {
    private $redis;
    
    public function get($key) {
        $cached = $this->redis->get($key);
        if ($cached !== false) {
            return json_decode($cached, true);
        }
        return null;
    }
    
    public function set($key, $value, $ttl = 3600) {
        $this->redis->setex($key, $ttl, json_encode($value));
    }
    
    public function invalidate($pattern) {
        $keys = $this->redis->keys($pattern);
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }
}

// Usage
$userService = new UserService($cache);
$user = $userService->getUser($userId); // Checks cache first
```

### 7. Testing

**Types of Tests:**
- Unit tests: Test individual functions/methods
- Integration tests: Test component interactions
- API tests: Test endpoints with various inputs
- Load tests: Test performance under load

**Testing Best Practices:**
- Write tests before or alongside code (TDD/BDD)
- Aim for high code coverage (80%+)
- Use test databases, not production
- Mock external dependencies
- Test edge cases and error conditions
- Automate test execution (CI/CD)

**Example: Unit Test (PHPUnit):**
```php
class UserServiceTest extends TestCase {
    private $userService;
    private $mockUserRepo;
    private $mockEmailService;
    
    protected function setUp(): void {
        $this->mockUserRepo = $this->createMock(UserRepository::class);
        $this->mockEmailService = $this->createMock(EmailService::class);
        $this->userService = new UserService($this->mockUserRepo, $this->mockEmailService);
    }
    
    public function testRegisterUserSuccess() {
        $userData = [
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
            'full_name' => 'Test User'
        ];
        
        $this->mockUserRepo->expects($this->once())
            ->method('findByEmail')
            ->willReturn(null);
            
        $this->mockUserRepo->expects($this->once())
            ->method('create')
            ->willReturn(new User($userData));
            
        $user = $this->userService->registerUser($userData);
        
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('test@example.com', $user->email);
    }
    
    public function testRegisterUserDuplicateEmail() {
        $this->expectException(ValidationException::class);
        
        $this->mockUserRepo->expects($this->once())
            ->method('findByEmail')
            ->willReturn(new User(['email' => 'test@example.com']));
            
        $this->userService->registerUser(['email' => 'test@example.com']);
    }
}
```

## Technology-Specific Guidelines

### PHP Best Practices

- Use PHP 8.0+ features (typed properties, named arguments, match expressions)
- Follow PSR standards (PSR-1, PSR-12 for coding style, PSR-4 for autoloading)
- Use Composer for dependency management
- Implement autoloading (PSR-4)
- Use type hints and return types
- Enable strict types (`declare(strict_types=1)`)
- Use PDO or modern ORMs (Eloquent, Doctrine)

### Node.js Best Practices

- Use async/await instead of callbacks
- Implement proper error handling in async code
- Use environment variables for configuration
- Implement graceful shutdown
- Use process managers (PM2) in production
- Handle uncaught exceptions and unhandled rejections
- Use TypeScript for type safety

### Python Best Practices

- Follow PEP 8 style guide
- Use virtual environments
- Implement type hints (Python 3.5+)
- Use context managers for resource management
- Leverage list comprehensions and generators
- Use async/await for I/O-bound operations
- Use SQLAlchemy or Django ORM for database operations

## Deployment & DevOps

**Environment Management:**
- Use separate environments (development, staging, production)
- Never commit secrets to version control
- Use environment variables or secret management services
- Implement proper CI/CD pipelines
- Automate deployments

**Monitoring & Observability:**
- Implement health check endpoints
- Monitor application metrics (response time, error rate, throughput)
- Set up alerts for critical issues
- Use APM tools (New Relic, DataDog, etc.)
- Implement distributed tracing for microservices

**Security in Production:**
- Keep dependencies updated
- Run security audits regularly
- Implement DDoS protection
- Use WAF (Web Application Firewall)
- Regular backups with tested restore procedures
- Implement disaster recovery plans

## Common Patterns & Anti-Patterns

**DO:**
- ✅ Use dependency injection
- ✅ Validate all inputs
- ✅ Use prepared statements
- ✅ Implement proper error handling
- ✅ Write tests
- ✅ Use version control
- ✅ Document your API
- ✅ Keep functions small and focused
- ✅ Use meaningful variable names
- ✅ Implement logging

**DON'T:**
- ❌ Trust user input
- ❌ Store passwords in plain text
- ❌ Use string concatenation for SQL queries
- ❌ Ignore errors silently
- ❌ Hardcode configuration values
- ❌ Return sensitive data in error messages
- ❌ Use global variables excessively
- ❌ Write god classes/functions
- ❌ Skip input validation
- ❌ Commit secrets to version control

## Conclusion

Building robust backend systems requires attention to security, performance, maintainability, and scalability. Always prioritize:

1. **Security first** - Validate inputs, use prepared statements, hash passwords, implement proper authentication
2. **Data integrity** - Design proper schemas, use transactions, implement constraints
3. **Error handling** - Catch exceptions, log errors, return meaningful messages
4. **Code quality** - Follow standards, write tests, use proper architecture
5. **Performance** - Optimize queries, implement caching, monitor metrics

Remember: The best backend is one that works reliably, scales appropriately, and can be maintained by your team. Don't over-engineer, but don't cut corners on security and data integrity.
