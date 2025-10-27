// Docker Bake configuration for building mail application images
// Update REPO variable with your own container registry URL

variable "REPO" {
  default = "ghcr.io/YOUR_GITHUB_USERNAME/mail"
}

variable "VERSION" {
  default = "dev"
}

target "default" {
  matrix = {
    item = [
      {
        name = "app"
        tags = ["${REPO}:latest", "${REPO}:${VERSION}"]
        platforms = ["linux/amd64", "linux/arm64"]
      },
      {
        name = "ci"
        tags = ["${REPO}:ci"]
        platforms = ["linux/amd64"]
      }
    ]
  }
  name = item.name
  dockerfile = "docker/${item.name}/Dockerfile"
  context = "."
  tags = item.tags
  platforms = item.platforms
  pull = true
}
