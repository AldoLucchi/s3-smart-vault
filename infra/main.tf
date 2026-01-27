provider "aws" {
  region = "us-east-1"
}

# El bucket donde se guardarán los archivos
resource "aws_s3_bucket" "smart_vault_storage" {
  bucket = "s3-smart-vault-storage-${random_id.suffix.hex}"
}

resource "random_id" "suffix" {
  byte_length = 4
}