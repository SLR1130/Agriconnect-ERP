const express = require("express");
const app = express();
const PORT = 5000;

app.get("/", (req, res) => {
  res.send("Hello! My Node.js application is running in Docker");
});

app.get("/api/health", (req, res) => {
  res.json({
    status: "ok",
    message: "Hello! My Node.js API is running in Docker"
  });
});

app.listen(PORT, "0.0.0.0", () => {
  console.log(`API running on port ${PORT}`);
});
