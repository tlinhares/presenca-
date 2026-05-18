            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script>
        $(document).ready(function() {
            // Código JavaScript personalizado aqui
            
            // Exemplo: Confirmação para exclusão
            $('.btn-delete').on('click', function(e) {
                if (!confirm('Tem certeza que deseja excluir este item?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
