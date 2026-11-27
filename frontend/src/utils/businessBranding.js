const palettes = [
  { ink: "#13362e", shadow: "#09201d", foil: "#dfae71", paper: "#fff4df" },
  { ink: "#43222f", shadow: "#28131e", foil: "#e9a68e", paper: "#fff0e9" },
  { ink: "#233753", shadow: "#131c34", foil: "#8fd9d7", paper: "#edfaff" },
  { ink: "#443223", shadow: "#251b15", foil: "#f0bd65", paper: "#fff3d8" },
  { ink: "#393058", shadow: "#201a3a", foil: "#c9b4f4", paper: "#f6eeff" },
  { ink: "#1d383b", shadow: "#10292d", foil: "#b9d88b", paper: "#f1f8e6" },
];

const serif = "Georgia, serif";

function designFor(name, variation) {
  let seed = 0;
  for (const character of name.normalize("NFC").toLowerCase()) {
    seed = (Math.imul(seed, 31) + character.codePointAt(0)) >>> 0;
  }
  const words = name.match(/[\p{L}\p{N}][\p{L}\p{N}\p{M}’']*/gu) || [];
  const first = words[0] || name;
  const characters =
    words.length > 1 ? [[...first][0], [...words.at(-1)][0]] : [...first].slice(0, 2);
  return {
    initials: characters.join("").toUpperCase(),
    palette: palettes[(seed + variation) % palettes.length],
    geometric: (seed + variation) % 2 === 0,
  };
}

function surface(width, height) {
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const context = canvas.getContext("2d");
  if (!context) throw new Error("Canvas is unavailable.");
  return { canvas, context };
}

function text(context, value, x, y, size, width, colour, family) {
  context.font = `600 ${size}px ${family}`;
  context.fillStyle = colour;
  context.textBaseline = "middle";
  context.fillText(value, x, y, width);
}

function mark(context, design, x, y, radius) {
  const { foil, paper } = design.palette;
  context.strokeStyle = foil;
  context.lineWidth = radius * 0.025;
  if (design.geometric) {
    const inset = radius * 0.79;
    context.strokeRect(x - inset, y - inset, inset * 2, inset * 2);
  } else {
    context.beginPath();
    context.arc(x, y, radius, 0, 2 * Math.PI);
    context.stroke();
  }
  context.textAlign = "center";
  text(context, design.initials, x, y + radius * 0.055, radius * 0.92, radius * 1.35, paper, serif);
}

function drawLogo(name, design) {
  const { canvas, context } = surface(960, 320);
  const { ink, foil, paper } = design.palette;
  context.fillStyle = ink;
  context.fillRect(0, 0, canvas.width, canvas.height);
  context.strokeStyle = foil;
  context.lineWidth = 3;
  context.strokeRect(16, 16, 928, 288);
  mark(context, design, 158, 160, 88);
  context.fillStyle = foil;
  context.fillRect(288, 101, 2, 118);
  context.textAlign = "left";
  text(context, name, 326, 165, 79, 585, paper, serif);
  return canvas.toDataURL("image/png");
}

function drawCover(name, design) {
  const { canvas, context } = surface(1280, 1280);
  const { ink, shadow, foil, paper } = design.palette;
  const background = context.createLinearGradient(0, 0, 1280, 1280);
  background.addColorStop(0, ink);
  background.addColorStop(1, shadow);
  context.fillStyle = background;
  context.fillRect(0, 0, canvas.width, canvas.height);

  context.save();
  context.translate(640, 640);
  context.rotate(Math.PI / 4);
  context.strokeStyle = foil;
  context.globalAlpha = 0.28;
  context.lineWidth = 2;
  for (let radius = 500; radius < 920; radius += 35) {
    if (design.geometric) {
      context.strokeRect(-radius, -radius, radius * 2, radius * 2);
    } else {
      context.beginPath();
      context.arc(0, 0, radius, 0, 2 * Math.PI);
      context.stroke();
    }
  }
  context.restore();

  context.strokeStyle = foil;
  context.lineWidth = 3;
  context.strokeRect(44, 44, 1192, 1192);
  mark(context, design, 640, 492, 130);
  text(context, name, 640, 731, 99, 680, paper, serif);
  context.fillStyle = foil;
  context.fillRect(602, 831, 76, 3);
  return canvas.toDataURL("image/jpeg", 0.92);
}

export function generateBusinessBranding(name, variation) {
  const design = designFor(name, variation);
  return { logo: drawLogo(name, design), cover: drawCover(name, design) };
}

export function brandingUpload(dataUrl, type) {
  const binary = atob(dataUrl.split(",")[1]);
  return new Blob([Uint8Array.from(binary, (character) => character.charCodeAt(0))], { type });
}
