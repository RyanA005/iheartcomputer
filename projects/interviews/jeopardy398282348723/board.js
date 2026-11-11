const VALUES = [200, 400, 600, 800, 1000];

const CATEGORIES = [
  {
    name: "Behavioral",
    cells: [
      ["Tell me about yourself.", "Why do you want to work here?"],
      ["Describe a time you worked on a team.", "Tell me about a project you're proud of."],
      ["Tell me about a time you disagreed with a teammate.", "Describe a time you had to learn something quickly."],
      ["Tell me about a time you failed. What did you learn?", "Tell me about a time you received difficult feedback."],
      ["Describe a time you made an important decision with incomplete information.", "Tell me about a time you had to balance competing priorities under a deadline."]
    ]
  },
  {
    name: "Technical Trivia",
    cells: [
      ["What's the difference between RAM and disk?", "What does HTTP stand for?"],
      ["What's the difference between a process and a thread?", "What's the difference between TCP and UDP?"],
      ["Explain a hash table and its average lookup complexity.", "What's the difference between a stack and a heap?"],
      ["What happens when you type a URL into your browser and press Enter?", "Explain what a database index does and its tradeoffs."],
      ["Explain how a race condition occurs and two ways to prevent one.", "What is a deadlock, and what conditions are necessary for one to occur?"]
    ]
  },
  {
    name: "System Design",
    cells: [
      ["Design a URL shortener. What components would you need?", "Design a simple to-do list application."],
      ["Design a website that lets students reserve study rooms.", "Design a basic real-time chat application."],
      ["Design a rate limiter for a public API.", "Design a notification system for a social media app."],
      ["Design a system that counts millions of unique visitors per day.", "Design a file upload and sharing service like Google Drive."],
      ["Design ChatGPT, how will you serve millions of requests across multiple models.", "Design a database especially for high connection counts."]
    ]
  },
  {
    name: "Coding",
    cells: [
      ["Write a function that reverses a string.", "Write a function that finds the largest number in an array."],
      ["Determine whether a string is a palindrome.", "Write a function that returns the sum of numbers up to N."],
      ["Implement binary search.", "Determine whether a string has balanced parentheses."],
      ["Determine if there are duplicates in an array in O(n) time.", "Implement radix sort."],
      ["Given an array and a target number, find two elements that add up to the target.", "You can climb either one or two stairs at a time. How many different ways can you reach the top of a staircase with N stairs."]
    ]
  },
  {
    name: "Wildcard",
    cells: [
      ["What questions should you ask an interviewer at the end of an interview?", "What should you do if you don't know the answer to a technical question?"],
      ["Estimate how many piano tuners work in New York City.", "How many tennis balls could fit in a school bus?"],
      ["Explain recursion to someone who has never programmed.", "Explain what an API is to a five-year-old."],
      ["You're interviewing and discover a bug in the interviewer's provided code. What do you do?", "Your solution works, but the interviewer says it's too slow. How do you respond?"],
      ["You're given a problem you believe is impossible under the stated constraints. How do you proceed?", "How does the chain rule work in calculus?"]
    ]
  }
];

function renderBoard(root) {
  root.replaceChildren();

  CATEGORIES.forEach((category) => {
    const cat = document.createElement("div");
    cat.className = "board-cat";
    cat.textContent = category.name;
    root.appendChild(cat);
  });

  VALUES.forEach((value, row) => {
    CATEGORIES.forEach((category, col) => {
      const [questionA, questionB] = category.cells[row];
      const button = document.createElement("button");
      button.type = "button";
      button.className = "cell";
      button.dataset.state = "0";
      button.dataset.value = String(value);
      button.dataset.a = questionA;
      button.dataset.b = questionB;
      button.setAttribute(
        "aria-label",
        `${category.name} $${value}. Click to reveal questions.`
      );
      paintCell(button);
      button.addEventListener("click", () => {
        const next = (Number(button.dataset.state) + 1) % 3;
        button.dataset.state = String(next);
        paintCell(button);
      });
      root.appendChild(button);
    });
  });
}

function paintCell(button) {
  const state = Number(button.dataset.state);
  const value = button.dataset.value;

  button.classList.remove("state-a", "state-b");

  if (state === 0) {
    button.textContent = `$${value}`;
    return;
  }

  button.classList.add(state === 1 ? "state-a" : "state-b");
  button.textContent = state === 1 ? button.dataset.a : button.dataset.b;
}

const board = document.getElementById("board");
if (board) renderBoard(board);
