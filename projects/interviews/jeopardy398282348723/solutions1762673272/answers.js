const VALUES = [200, 400, 600, 800, 1000];

const CATEGORIES = [
  {
    name: "Behavioral",
    cells: [
      {
        a: {
          q: "Tell me about yourself.",
          blocks: [
            { type: "p", text: "A good response should take approximately 30-60 seconds and include:" },
            { type: "ul", items: [
              "Current academic or professional background.",
              "Relevant technical interests and experience.",
              "What they're looking to do next."
            ]},
            { type: "p", text: 'Example: "I\'m a junior studying computer science, particularly interested in systems programming. Recently, I built a small web application with a friend, where I handled the backend. I\'m looking for opportunities to work on larger systems and learn from experienced engineers."' }
          ]
        },
        b: {
          q: "Why do you want to work here?",
          blocks: [
            { type: "p", text: "A good response connects three things: the company's work, the candidate's interests, and what they can contribute." },
            { type: "p", text: "Full credit requires a specific reason beyond salary, prestige, or simply needing employment." }
          ]
        }
      },
      {
        a: {
          q: "Describe a time you worked on a team.",
          blocks: [
            { type: "p", text: "Expected: Explain the project, the candidate's responsibilities, how the team coordinated, and what the candidate contributed." },
            { type: "p", text: "Look for clear ownership, cooperation, and a result." }
          ]
        },
        b: {
          q: "Tell me about a project you're proud of.",
          blocks: [
            { type: "p", text: "Expected: Explain the project's purpose, the technical approach, the candidate's personal contribution, and why it matters to them." },
            { type: "p", text: "Bonus consideration for discussing technical challenges or lessons learned." }
          ]
        }
      },
      {
        a: {
          q: "Tell me about a time you disagreed with a teammate.",
          blocks: [
            { type: "p", text: "Expected: Describe the disagreement respectfully, explain how different viewpoints were evaluated, and describe the resolution." },
            { type: "p", text: "A strong answer demonstrates listening, compromise, and evidence-based decision-making." }
          ]
        },
        b: {
          q: "Describe a time you had to learn something quickly.",
          blocks: [
            { type: "p", text: "Expected: Identify the unfamiliar subject, explain the learning strategy, and describe how that knowledge was applied." },
            { type: "p", text: "A strong answer shows initiative and an ability to learn independently." }
          ]
        }
      },
      {
        a: {
          q: "Tell me about a time you failed. What did you learn?",
          blocks: [
            { type: "p", text: "Expected: A genuine failure, ownership of the candidate's contribution, and a concrete change in behavior." },
            { type: "p", text: "Avoid answers that disguise strengths as weaknesses." }
          ]
        },
        b: {
          q: "Tell me about a time you received difficult feedback.",
          blocks: [
            { type: "p", text: "Expected: Explain the feedback, how the candidate reacted, and what changed afterward." },
            { type: "p", text: "Strong answers demonstrate openness to criticism and measurable improvement." }
          ]
        }
      },
      {
        a: {
          q: "Describe a time you made an important decision with incomplete information.",
          blocks: [
            { type: "p", text: "Expected: Explain the uncertainty, available evidence, alternatives, risks, and eventual outcome." },
            { type: "p", text: "A strong answer demonstrates structured reasoning rather than guessing." }
          ]
        },
        b: {
          q: "Tell me about a time you had to balance competing priorities under a deadline.",
          blocks: [
            { type: "p", text: "Expected: Describe how tasks were prioritized, what tradeoffs were made, and how expectations were communicated." },
            { type: "p", text: "Strong answers identify urgency versus importance and demonstrate deliberate prioritization." }
          ]
        }
      }
    ]
  },
  {
    name: "Technical Trivia",
    cells: [
      {
        a: {
          q: "What's the difference between RAM and disk?",
          blocks: [
            { type: "ul", items: [
              "RAM: Fast, volatile working memory used to hold active programs and data.",
              "Disk: Nonvolatile storage, such as an SSD or HDD, that retains data when power is removed."
            ]},
            { type: "p", text: "RAM is generally much faster, but more expensive per byte." }
          ]
        },
        b: {
          q: "What does HTTP stand for?",
          blocks: [
            { type: "p", text: "Hypertext Transfer Protocol." },
            { type: "p", text: "It is an application-layer protocol used for communication between web clients and servers." }
          ]
        }
      },
      {
        a: {
          q: "What's the difference between a process and a thread?",
          blocks: [
            { type: "ul", items: [
              "Process: An executing program with its own virtual address space and resources.",
              "Thread: An execution unit within a process that shares the process's address space and many resources with other threads."
            ]},
            { type: "p", text: "Threads typically have lower creation and communication overhead, but shared memory introduces synchronization concerns." }
          ]
        },
        b: {
          q: "What's the difference between TCP and UDP?",
          blocks: [
            { type: "ul", items: [
              "TCP: Connection-oriented, reliable, ordered byte stream.",
              "UDP: Connectionless datagrams without built-in guarantees of delivery or ordering."
            ]},
            { type: "p", text: "TCP provides retransmission and flow/congestion control. UDP has less protocol overhead and gives applications more control." }
          ]
        }
      },
      {
        a: {
          q: "Explain a hash table and its average lookup complexity.",
          blocks: [
            { type: "p", text: "A hash table uses a hash function to map a key to a bucket or location." },
            { type: "ul", items: [
              "Average lookup: O(1) under standard hashing assumptions.",
              "Worst-case lookup: O(n) when collisions are poorly distributed.",
              "Collisions can be handled using chaining or open addressing."
            ]}
          ]
        },
        b: {
          q: "What's the difference between a stack and a heap?",
          blocks: [
            { type: "p", text: "For program memory:" },
            { type: "ul", items: [
              "Stack: Typically stores function call frames, local variables, and return information. Allocation is usually automatic and follows function lifetimes.",
              "Heap: Supports dynamic allocation with lifetimes not necessarily tied to function calls."
            ]},
            { type: "p", text: "Heap allocation generally involves more management overhead. Stack memory is usually more limited." },
            { type: "p", text: "Accept a clarification that stack and heap also refer to data structures." }
          ]
        }
      },
      {
        a: {
          q: "What happens when you type a URL into your browser and press Enter?",
          blocks: [
            { type: "p", text: "Expected sequence:" },
            { type: "ol", items: [
              "Browser parses the URL.",
              "Resolves the domain name using DNS if necessary.",
              "Establishes a connection using TCP or QUIC.",
              "Negotiates TLS for HTTPS.",
              "Sends an HTTP request.",
              "Server processes the request and responds.",
              "Browser parses HTML, retrieves additional resources, executes JavaScript, and renders the page."
            ]},
            { type: "p", text: "Caching and existing connections may skip some steps." }
          ]
        },
        b: {
          q: "Explain what a database index does and its tradeoffs.",
          blocks: [
            { type: "p", text: "An index is an auxiliary data structure, often a B-tree or B+ tree, used to locate records efficiently." },
            { type: "p", text: "Benefits:" },
            { type: "ul", items: [
              "Faster lookups and some sorts and joins.",
              "Can avoid full table scans."
            ]},
            { type: "p", text: "Tradeoffs:" },
            { type: "ul", items: [
              "Additional storage.",
              "Slower writes because indexes must be maintained.",
              "Additional planning and maintenance complexity."
            ]},
            { type: "p", text: "A B-tree lookup is typically O(log n)." }
          ]
        }
      },
      {
        a: {
          q: "Explain how a race condition occurs and two ways to prevent one.",
          blocks: [
            { type: "p", text: "A race condition occurs when a program's behavior depends on the timing or ordering of concurrent operations." },
            { type: "p", text: "Example: Two threads both read a counter value of 5, increment it, and write 6 instead of producing 7." },
            { type: "p", text: "Two solutions:" },
            { type: "ol", items: [
              "Mutex: Protect the shared operation with a lock.",
              "Atomic operation: Use an atomic increment."
            ]},
            { type: "p", text: "Other acceptable answers include appropriate message passing, synchronization primitives, or avoiding shared mutable state." }
          ]
        },
        b: {
          q: "What is a deadlock, and what conditions are necessary for one to occur?",
          blocks: [
            { type: "p", text: "A deadlock occurs when operations cannot proceed because they are waiting on one another." },
            { type: "p", text: "The four Coffman conditions are:" },
            { type: "ol", items: [
              "Mutual exclusion: Some resource cannot be shared.",
              "Hold and wait: An operation holds resources while requesting others.",
              "No preemption: Held resources cannot be forcibly reclaimed.",
              "Circular wait: Operations form a cycle of resource dependencies."
            ]},
            { type: "p", text: "Preventing any one of these conditions can prevent deadlock." }
          ]
        }
      }
    ]
  },
  {
    name: "System Design",
    cells: [
      {
        a: {
          q: "Design a URL shortener. What components would you need?",
          blocks: [
            { type: "p", text: "Expected architecture:" },
            { type: "ol", items: [
              "API accepts a long URL.",
              "Generate a unique short identifier.",
              "Store the identifier-to-URL mapping in a database.",
              "On a request to the short URL, look up the destination and issue an HTTP redirect."
            ]},
            { type: "p", text: "A strong answer discusses collisions, caching, and expiration." }
          ]
        },
        b: {
          q: "Design a simple to-do list application.",
          blocks: [
            { type: "p", text: "Expected components:" },
            { type: "ul", items: [
              "Frontend displaying tasks.",
              "Backend API for creating, reading, updating, and deleting tasks.",
              "Database storing tasks and their completion status."
            ]},
            { type: "p", text: "Possible data model: Task(id, user_id, title, completed, created_at)." },
            { type: "p", text: "Bonus: User authentication and ensuring users can access only their own tasks." }
          ]
        }
      },
      {
        a: {
          q: "Design a website that lets students reserve study rooms.",
          blocks: [
            { type: "p", text: "Expected components:" },
            { type: "ol", items: [
              "Frontend displaying rooms and available times.",
              "API to create, view, and cancel reservations.",
              "Database storing rooms, students, and bookings.",
              "Authentication using university credentials."
            ]},
            { type: "p", text: "Important challenge: Prevent double-booking with database constraints or transactions." }
          ]
        },
        b: {
          q: "Design a basic real-time chat application.",
          blocks: [
            { type: "p", text: "Expected components:" },
            { type: "ul", items: [
              "WebSocket connections between clients and servers.",
              "Server handling incoming messages.",
              "Database storing message history.",
              "Mechanism for delivering messages to connected recipients."
            ]},
            { type: "p", text: "Bonus: Multiple servers can use a message broker or pub/sub system to distribute messages." }
          ]
        }
      },
      {
        a: {
          q: "Design a rate limiter for a public API.",
          blocks: [
            { type: "p", text: "Expected approach: Token bucket." },
            { type: "ol", items: [
              "Each user has a bucket of available request tokens.",
              "Tokens replenish at a fixed rate up to a maximum.",
              "Each request consumes a token.",
              "Requests without available tokens receive HTTP 429."
            ]},
            { type: "p", text: "Bonus: Use Redis and atomic operations to share rate-limit state across multiple API servers." }
          ]
        },
        b: {
          q: "Design a notification system for a social media app.",
          blocks: [
            { type: "p", text: "Expected architecture:" },
            { type: "ol", items: [
              "User actions generate events.",
              "Events enter a message queue.",
              "Workers consume events.",
              "Workers determine recipients and notification preferences.",
              "Notifications are delivered through push, email, or an in-app inbox."
            ]},
            { type: "p", text: "Bonus: Retries, deduplication, and avoiding duplicate notifications." }
          ]
        }
      },
      {
        a: {
          q: "Design a system that counts millions of unique visitors per day.",
          blocks: [
            { type: "p", text: "Expected architecture:" },
            { type: "ol", items: [
              "Websites send visitor events to an ingestion API.",
              "Events are written into a queue or event stream.",
              "Distributed workers process the events.",
              "Visitors are identified using an appropriate cookie or pseudonymous ID.",
              "Unique IDs are counted per website per day.",
              "Aggregated statistics are stored for fast dashboard queries."
            ]},
            { type: "p", text: "A strong answer mentions HyperLogLog, which can estimate distinct counts using relatively little memory." },
            { type: "p", text: "Bonus: Discuss time zones, bots, privacy, and exact versus approximate counting." }
          ]
        },
        b: {
          q: "Design a file upload and sharing service like Google Drive.",
          blocks: [
            { type: "p", text: "Expected architecture:" },
            { type: "ul", items: [
              "Authentication and authorization service.",
              "Metadata database for file names, ownership, and permissions.",
              "Object storage for actual file contents.",
              "Upload and download API.",
              "Share links or access-control lists."
            ]},
            { type: "p", text: "A strong answer uses multipart uploads for large files and signed URLs for direct transfers to object storage." },
            { type: "p", text: "Bonus: Version history, deduplication, encryption, and replication." }
          ]
        }
      },
      {
        a: {
          q: "Design ChatGPT, how will you serve millions of requests across multiple models.",
          blocks: [
            { type: "p", text: "Expected architecture:" },
            { type: "ol", items: [
              "API gateway: Authenticates requests and applies rate limits.",
              "Model router: Selects the correct model based on request requirements.",
              "Request scheduler: Queues and batches inference requests.",
              "GPU worker pools: Host model weights and perform inference.",
              "Streaming service: Returns tokens to clients as they are generated.",
              "Monitoring: Tracks latency, GPU utilization, queue length, and errors."
            ]},
            { type: "p", text: "Important tradeoffs:" },
            { type: "ul", items: [
              "Larger models consume more GPU memory.",
              "Different models require different hardware resources.",
              "Batching improves throughput but can increase latency.",
              "Long requests can monopolize compute.",
              "Requests may need routing based on model availability and capacity."
            ]},
            { type: "p", text: "Full credit: A coherent distributed inference architecture that handles routing, scaling, and GPU scheduling." }
          ]
        },
        b: {
          q: "Design a database especially for high connection counts.",
          blocks: [
            { type: "p", text: "Expected architecture:" },
            { type: "ol", items: [
              "Connection gateway accepts client connections.",
              "Authentication and authorization run at the gateway.",
              "Connection pooling limits the number of backend database connections.",
              "Requests are multiplexed over available backend connections when transaction semantics allow.",
              "Read replicas distribute read workloads.",
              "Database instances can be partitioned or sharded as necessary."
            ]},
            { type: "p", text: "A strong answer distinguishes the number of connected clients from the number of concurrently executing queries." },
            { type: "p", text: "Bonus: Discuss asynchronous I/O, backpressure, idle connections, and transaction pooling limitations." }
          ]
        }
      }
    ]
  },
  {
    name: "Coding",
    cells: [
      {
        a: {
          q: "Write a function that reverses a string.",
          blocks: [
            { type: "code", text: "def reverse_string(s):\n    return s[::-1]" },
            { type: "p", text: 'Example: "hello" → "olleh"' },
            { type: "p", text: "Time: O(n). Space: O(n) for the returned string." }
          ]
        },
        b: {
          q: "Write a function that finds the largest number in an array.",
          blocks: [
            { type: "code", text: "def find_max(arr):\n    if not arr:\n        return None\n    largest = arr[0]\n    for x in arr:\n        if x > largest:\n            largest = x\n    return largest" },
            { type: "p", text: "Time: O(n). Space: O(1)." }
          ]
        }
      },
      {
        a: {
          q: "Determine whether a string is a palindrome.",
          blocks: [
            { type: "code", text: "def is_palindrome(s):\n    return s == s[::-1]" },
            { type: "p", text: 'Examples: "racecar" → True, "hello" → False.' },
            { type: "p", text: "Time: O(n). This version is case-sensitive and treats spaces as characters." }
          ]
        },
        b: {
          q: "Write a function that returns the sum of numbers up to N.",
          blocks: [
            { type: "code", text: "def sum_to_n(n):\n    return n * (n + 1) // 2" },
            { type: "p", text: "Formula:" },
            { type: "formula", text: "sum(i=1..n) i = n(n+1)/2" },
            { type: "p", text: "Time: O(1), assuming fixed-width arithmetic." }
          ]
        }
      },
      {
        a: {
          q: "Implement binary search.",
          blocks: [
            { type: "code", text: "def binary_search(arr, target):\n    left, right = 0, len(arr) - 1\n    while left <= right:\n        mid = (left + right) // 2\n        if arr[mid] == target:\n            return mid\n        elif arr[mid] < target:\n            left = mid + 1\n        else:\n            right = mid - 1\n    return -1" },
            { type: "p", text: "Requires a sorted array." },
            { type: "p", text: "Time: O(log n). Space: O(1)." }
          ]
        },
        b: {
          q: "Determine whether a string has balanced parentheses.",
          blocks: [
            { type: "code", text: "def balanced(s):\n    stack = []\n    pairs = {')': '(', ']': '[', '}': '{'}\n    for c in s:\n        if c in \"([{\":\n            stack.append(c)\n        elif c in pairs:\n            if not stack or stack.pop() != pairs[c]:\n                return False\n    return len(stack) == 0" },
            { type: "p", text: 'Examples: "([])" → True, "([)]" → False.' },
            { type: "p", text: "Time: O(n). Space: O(n)." }
          ]
        }
      },
      {
        a: {
          q: "Determine if there are duplicates in an array in O(n) time.",
          blocks: [
            { type: "code", text: "def has_duplicates(arr):\n    seen = set()\n    for x in arr:\n        if x in seen:\n            return True\n        seen.add(x)\n    return False" },
            { type: "p", text: "Uses a hash set to track previously encountered values." },
            { type: "p", text: "Time: O(n) expected. Space: O(n)." }
          ]
        },
        b: {
          q: "Implement radix sort.",
          blocks: [
            { type: "p", text: "This example implements least-significant-digit radix sort for nonnegative integers." },
            { type: "code", text: "def radix_sort(arr):\n    if not arr:\n        return []\n    result = arr[:]\n    exp = 1\n    maximum = max(result)\n    while maximum // exp > 0:\n        buckets = [[] for _ in range(10)]\n        for x in result:\n            digit = (x // exp) % 10\n            buckets[digit].append(x)\n        result = [\n            x for bucket in buckets for x in bucket\n        ]\n        exp *= 10\n    return result" },
            { type: "p", text: "Example: [170, 45, 75, 90, 2] → [2, 45, 75, 90, 170]" },
            { type: "p", text: "Explanation: Sort numbers by their ones digit, then tens digit, hundreds digit, and so on. Each pass must preserve the previous ordering of elements with equal current digits." },
            { type: "p", text: "Time: O(d(n + 10)), where d is the maximum number of decimal digits. Space: O(n + 10)." }
          ]
        }
      },
      {
        a: {
          q: "Given an array and a target number, find two elements that add up to the target.",
          blocks: [
            { type: "code", text: "def two_sum(arr, target):\n    seen = set()\n    for x in arr:\n        complement = target - x\n        if complement in seen:\n            return (complement, x)\n        seen.add(x)\n    return None" },
            { type: "p", text: "Example: arr = [2, 7, 11, 15], target = 9 → (2, 7)." },
            { type: "p", text: "Time: O(n) expected. Space: O(n)." },
            { type: "p", text: "The important insight is that a hash set avoids the O(n²) nested-loop solution." }
          ]
        },
        b: {
          q: "You can climb either one or two stairs at a time. How many different ways can you reach the top of a staircase with N stairs.",
          blocks: [
            { type: "code", text: "def climb_stairs(n):\n    if n < 0:\n        return 0\n    a, b = 1, 1\n    for _ in range(n):\n        a, b = b, a + b\n    return a" },
            { type: "p", text: "Examples:" },
            { type: "code", text: "N  Ways\n1  1\n2  2\n3  3\n4  5\n5  8" },
            { type: "p", text: "Explanation: The last move must have been either one step or two steps. Therefore:" },
            { type: "formula", text: "f(n) = f(n-1) + f(n-2)" },
            { type: "p", text: "With f(0)=1 and f(1)=1, this is the Fibonacci recurrence." },
            { type: "p", text: "Time: O(n). Space: O(1), excluding integer-size growth." }
          ]
        }
      }
    ]
  },
  {
    name: "Wildcard",
    cells: [
      {
        a: {
          q: "What questions should you ask an interviewer at the end of an interview?",
          blocks: [
            { type: "p", text: "Good examples:" },
            { type: "ul", items: [
              "What would success look like in this role after six months?",
              "What technical challenges is your team currently facing?",
              "What does the development and code-review process look like?",
              "What do you enjoy most about working here?"
            ]},
            { type: "p", text: "Full credit: Two or more thoughtful questions demonstrating genuine interest in the role." }
          ]
        },
        b: {
          q: "What should you do if you don't know the answer to a technical question?",
          blocks: [
            { type: "ol", items: [
              "Admit what you don't know.",
              "Explain what you do understand.",
              "Ask clarifying questions.",
              "Break the problem into smaller pieces.",
              "Explain how you would investigate or solve it."
            ]},
            { type: "p", text: "Full credit: Demonstrates honest communication and problem-solving rather than bluffing." }
          ]
        }
      },
      {
        a: {
          q: "Estimate how many piano tuners work in New York City.",
          blocks: [
            { type: "p", text: "This is a Fermi estimation question. No single number is expected." },
            { type: "p", text: "Example approach:" },
            { type: "ul", items: [
              "NYC has roughly 8 million residents.",
              "Assume 3 million households.",
              "Suppose 5% own a piano: 150,000 pianos.",
              "Assume each piano is tuned once per year.",
              "Suppose a piano tuner completes 1,000 tunings annually."
            ]},
            { type: "p", text: "Estimated answer: 150 piano tuners, excluding commercial demand and other adjustments." },
            { type: "p", text: "Full credit: Reasonable assumptions, coherent calculations, and an awareness of uncertainty." }
          ]
        },
        b: {
          q: "How many tennis balls could fit in a school bus?",
          blocks: [
            { type: "p", text: "Example approach:" },
            { type: "ul", items: [
              "Approximate bus interior volume: 50 cubic meters.",
              "Tennis ball diameter: 6.7 cm.",
              "Volume per ball: approximately 0.000157 cubic meters.",
              "Account for gaps between balls with a packing efficiency of approximately 64%."
            ]},
            { type: "formula", text: "(50 * 0.64) / 0.000157 ≈ 204,000" },
            { type: "p", text: "Estimated answer: 200,000 tennis balls, assuming an empty interior." },
            { type: "p", text: "Full credit: Plausible bus dimensions, tennis ball size, and consideration of empty space." }
          ]
        }
      },
      {
        a: {
          q: "Explain recursion to someone who has never programmed.",
          blocks: [
            { type: "p", text: 'Example answer: "Recursion is when you solve a problem by solving a smaller version of the same problem."' },
            { type: "p", text: "For example, opening a Russian nesting doll: Each doll contains a smaller doll. You repeat the same action until you reach one that cannot be opened." },
            { type: "p", text: "Important concepts:" },
            { type: "ul", items: [
              "Recursive step: Solve a smaller version of the problem.",
              "Base case: Know when to stop."
            ]}
          ]
        },
        b: {
          q: "Explain what an API is to a five-year-old.",
          blocks: [
            { type: "p", text: 'Example answer: "Imagine you\'re at a restaurant. You tell the waiter what food you want. The waiter tells the kitchen, and the kitchen makes it. Then the waiter brings your food. An API is like the waiter: it lets one computer program ask another program to do something."' },
            { type: "p", text: "Full credit: A clear analogy that explains requests, responses, and interaction between programs." }
          ]
        }
      },
      {
        a: {
          q: "You're interviewing and discover a bug in the interviewer's provided code. What do you do?",
          blocks: [
            { type: "p", text: "Expected approach:" },
            { type: "ol", items: [
              "Verify that the behavior is actually a bug.",
              "Politely point out the issue.",
              "Explain why it occurs.",
              "Suggest a correction.",
              "Continue solving the original problem."
            ]},
            { type: "p", text: "Full credit: Demonstrates technical confidence and professional communication without becoming confrontational." }
          ]
        },
        b: {
          q: "Your solution works, but the interviewer says it's too slow. How do you respond?",
          blocks: [
            { type: "p", text: "Expected approach:" },
            { type: "ol", items: [
              "Analyze the current time complexity.",
              "Identify the expensive operation or bottleneck.",
              "Consider a more efficient data structure or algorithm.",
              "Explain the time-space tradeoffs.",
              "Implement or describe an improved approach."
            ]},
            { type: "p", text: "Example: Replacing an O(n²) nested-loop search with an O(n) hash-set solution." },
            { type: "p", text: "Full credit: An analytical response that demonstrates willingness to improve the solution." }
          ]
        }
      },
      {
        a: {
          q: "You're given a problem you believe is impossible under the stated constraints. How do you proceed?",
          blocks: [
            { type: "p", text: "Expected approach:" },
            { type: "ol", items: [
              "Restate the constraints to confirm understanding.",
              "Identify which constraint makes the problem impossible.",
              "Explain or demonstrate why.",
              "Offer a counterexample or proof if possible.",
              "Suggest a modified constraint or an approximate solution."
            ]},
            { type: "p", text: "Full credit: Recognizing that some problems are impossible as stated and communicating that conclusion with evidence." }
          ]
        },
        b: {
          q: "How does the chain rule work in calculus?",
          blocks: [
            { type: "p", text: "The chain rule describes how to differentiate a composition of functions." },
            { type: "p", text: "If y = f(g(x)), then:" },
            { type: "formula", text: "dy/dx = f'(g(x)) * g'(x)" },
            { type: "p", text: "For example, y = (3x+2)^4. Differentiate the outside function, keeping the inside unchanged, then multiply by the derivative of the inside:" },
            { type: "formula", text: "dy/dx = 4(3x+2)^3 * 3 = 12(3x+2)^3" },
            { type: "p", text: "Full credit: States the chain rule correctly and applies it to a composite function." }
          ]
        }
      }
    ]
  }
];
