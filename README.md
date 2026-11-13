# Plugin Quadros para GLPI

Plugin para o **GLPI 11** com quadros de tarefas no estilo **Trello** (Kanban): quadros com listas (colunas) e cartões que você arrasta entre as listas.

## Funcionalidades

- **Quadros**: crie vários quadros; cada novo quadro já vem com as listas *A fazer*, *Em andamento* e *Concluído*.
- **Listas**: adicionar, renomear (clique no título), excluir e **reordenar arrastando pelo cabeçalho**.
- **Cartões**: adicionar rapidamente em cada lista e **arrastar entre listas** (a posição é salva na hora).
- **Detalhes do cartão** (clique no cartão): título, descrição, **prazo**, **cor**, marcação de **concluído** e **membros** (usuários do GLPI, com busca).
- Selos nos cartões: prazo (vermelho se vencido, amarelo se vence em até 1 dia, verde se concluído), ícone de descrição e avatares dos membros.
- Permissões por perfil: quem tem apenas leitura vê o quadro, mas não edita (aba *Quadros* em Administração > Perfis).
- Histórico de alterações do quadro e controle por entidade, como os demais itens do GLPI.
- Não depende de bibliotecas externas: usa o `html5sortable`, o jQuery e o Bootstrap que já vêm com o GLPI.

## Requisitos

- GLPI 11.0.x
- PHP 8.2 ou superior

## Instalação

1. Copie a pasta para o diretório de plugins do GLPI. O nome da pasta deve ser `quadros`:

   ```bash
   cd /var/www/html/glpi/plugins
   git clone https://github.com/SEU-USUARIO/glpi-plugin-quadros.git quadros
   chown -R www-data:www-data quadros
   ```

2. No GLPI, vá em **Configurar > Plugins**, clique em **Instalar** e depois em **Ativar**. Ou pelo console:

   ```bash
   sudo -u www-data php bin/console glpi:plugin:install quadros --username=<usuario>
   sudo -u www-data php bin/console glpi:plugin:activate quadros
   ```

3. Em **Administração > Perfis**, abra o perfil desejado, aba **Quadros**, e conceda os direitos.
   Na instalação, o direito completo é concedido ao perfil que instalou (ou ao *Super-Admin*, se instalado pelo console).

4. Acesse em **Ferramentas > Quadros**.

## Como usar

- **Ferramentas > Quadros > Adicionar** cria um quadro. Clicar em um quadro da lista abre o Kanban.
- Use **Configurações** (no topo do quadro) para renomear, mudar a entidade, editar o comentário ou excluir o quadro.
- Todos os usuários com direito de leitura na entidade enxergam o quadro; quem tem direito de atualização edita.

## Atualização

Ao mudar a versão do plugin, o GLPI o coloca em "Para atualizar" e o menu some até a atualização. Depois de atualizar os arquivos, clique em **Instalar/Atualizar** e **Ativar** em *Configurar > Plugins* (ou use os comandos do console acima).

## Estrutura

```
quadros/
├── setup.php           # registro, versão, menu
├── hook.php            # instalação/remoção das tabelas
├── ajax/quadro.php     # endpoint JSON (POST, protegido por CSRF)
├── front/              # lista, formulário e tela do quadro
├── src/                # Quadro, Lista, Cartao, Profile, Api
└── templates/          # formulário e tela do Kanban (Twig + CSS + JS)
```

## Tabelas

- `glpi_plugin_quadros_quadros`
- `glpi_plugin_quadros_listas`
- `glpi_plugin_quadros_cartoes`
- `glpi_plugin_quadros_cartoes_users`

A desinstalação remove essas tabelas e os direitos do plugin.

## Segurança

- Todas as ações passam pela classe `Api`, que valida permissão (leitura/atualização) e que a lista ou o cartão pertence ao quadro informado.
- O endpoint aceita apenas POST e exige o token CSRF do GLPI.
- Títulos e descrições são exibidos como texto (nunca como HTML).

## Licença

GPLv2+ (mesma licença do GLPI). Adicione o arquivo `LICENSE` antes de publicar, se desejar.
