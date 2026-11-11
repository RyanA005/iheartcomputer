let selected = null;
let selectedSide = null;

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
      const button = document.createElement("button");
      button.type = "button";
      button.className = "cell";
      button.textContent = `$${value}`;
      button.dataset.col = String(col);
      button.dataset.row = String(row);
      button.setAttribute(
        "aria-label",
        `${category.name} $${value}. Select to view answers.`
      );
      button.addEventListener("click", () => selectCell(col, row));
      root.appendChild(button);
    });
  });
}

function selectCell(col, row) {
  selected = { col, row };
  selectedSide = null;

  document.querySelectorAll(".board-select .cell").forEach((cell) => {
    const match =
      Number(cell.dataset.col) === col && Number(cell.dataset.row) === row;
    cell.classList.toggle("selected", match);
    cell.classList.toggle("state-a", match);
  });

  const category = CATEGORIES[col];
  const value = VALUES[row];
  const cell = category.cells[row];

  const picker = document.getElementById("picker");
  const meta = document.getElementById("picker-meta");
  const choices = document.getElementById("picker-choices");
  const answer = document.getElementById("answer");

  picker.hidden = false;
  answer.hidden = true;
  answer.replaceChildren();

  meta.textContent = `${category.name} $${value}`;

  choices.replaceChildren();
  ["a", "b"].forEach((side) => {
    const choice = document.createElement("button");
    choice.type = "button";
    choice.className = "choice";
    choice.dataset.side = side;

    const tag = document.createElement("span");
    tag.className = "tag";
    tag.textContent = side === "a" ? "Question A" : "Question B";

    const text = document.createElement("span");
    text.textContent = cell[side].q;

    choice.append(tag, text);
    choice.addEventListener("click", () => selectSide(side));
    choices.appendChild(choice);
  });

  picker.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

function selectSide(side) {
  selectedSide = side;
  const { col, row } = selected;
  const category = CATEGORIES[col];
  const value = VALUES[row];
  const entry = category.cells[row][side];

  document.querySelectorAll(".choice").forEach((choice) => {
    choice.classList.toggle("selected", choice.dataset.side === side);
  });

  document.querySelectorAll(".board-select .cell").forEach((cell) => {
    const match =
      Number(cell.dataset.col) === col && Number(cell.dataset.row) === row;
    cell.classList.toggle("state-a", match && side === "a");
    cell.classList.toggle("state-b", match && side === "b");
  });

  const answer = document.getElementById("answer");
  answer.hidden = false;
  answer.replaceChildren();

  const title = document.createElement("h2");
  title.textContent = `${category.name} $${value} ${side === "a" ? "A" : "B"}`;

  const question = document.createElement("p");
  question.className = "question";
  question.textContent = entry.q;

  answer.append(title, question);
  entry.blocks.forEach((block) => answer.append(renderBlock(block)));
  answer.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

function renderBlock(block) {
  if (block.type === "p") {
    const p = document.createElement("p");
    p.textContent = block.text;
    return p;
  }

  if (block.type === "ul" || block.type === "ol") {
    const list = document.createElement(block.type);
    block.items.forEach((item) => {
      const li = document.createElement("li");
      li.textContent = item;
      list.appendChild(li);
    });
    return list;
  }

  if (block.type === "code") {
    const pre = document.createElement("pre");
    const code = document.createElement("code");
    code.textContent = block.text;
    pre.appendChild(code);
    return pre;
  }

  if (block.type === "formula") {
    const div = document.createElement("div");
    div.className = "formula";
    div.textContent = block.text;
    return div;
  }

  const fallback = document.createElement("p");
  fallback.textContent = block.text || "";
  return fallback;
}

const board = document.getElementById("board");
if (board) renderBoard(board);
