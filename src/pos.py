# Simple Point of Sale system for a restaurant

class MenuItem:
    def __init__(self, name, price):
        self.name = name
        self.price = price

    def __str__(self):
        return f"{self.name}: ${self.price:.2f}"

class Order:
    def __init__(self):
        self.items = []

    def add_item(self, item):
        self.items.append(item)

    def total(self):
        return sum(item.price for item in self.items)

    def summary(self):
        lines = [str(item) for item in self.items]
        lines.append(f"Total: ${self.total():.2f}")
        return "\n".join(lines)

class POS:
    def __init__(self):
        self.menu = [
            MenuItem("Hamburguesa", 8.50),
            MenuItem("Pizza", 9.99),
            MenuItem("Ensalada", 6.25),
            MenuItem("Refresco", 2.00),
        ]
        self.order = Order()

    def show_menu(self):
        print("\nMenu:")
        for index, item in enumerate(self.menu, start=1):
            print(f"{index}. {item}")
        print("0. Finalizar pedido")

    def run(self):
        print("Sistema de Punto de Venta - Restaurante")
        while True:
            self.show_menu()
            choice = input("Seleccione un artículo por número (0 para finalizar): ")
            if choice == "0":
                break
            try:
                index = int(choice) - 1
                if 0 <= index < len(self.menu):
                    item = self.menu[index]
                    self.order.add_item(item)
                    print(f"Agregado {item.name}")
                else:
                    print("Selección inválida")
            except ValueError:
                print("Ingrese un número válido")
        print("\nResumen del pedido:")
        print(self.order.summary())
        pago = input("Ingrese monto de pago: $")
        try:
            pago = float(pago)
            cambio = pago - self.order.total()
            if cambio < 0:
                print("Monto insuficiente. Pedido cancelado.")
            else:
                print(f"Cambio: ${cambio:.2f}")
                print("¡Gracias por su compra!")
        except ValueError:
            print("Pago inválido. Pedido cancelado.")

if __name__ == "__main__":
    POS().run()
