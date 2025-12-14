import json
import os
from datetime import datetime, timedelta

class LibraryLenderSystem:
    def __init__(self):
        self.books_file = "books.json"
        self.users_file = "users.json"
        self.requests_file = "requests.json"
        self.load_data()
    
    def load_data(self):
        # Load books
        if os.path.exists(self.books_file):
            with open(self.books_file, 'r') as f:
                self.books = json.load(f)
        else:
            self.books = []
        
        # Load users
        if os.path.exists(self.users_file):
            with open(self.users_file, 'r') as f:
                self.users = json.load(f)
        else:
            self.users = []
        
        # Load requests
        if os.path.exists(self.requests_file):
            with open(self.requests_file, 'r') as f:
                self.requests = json.load(f)
        else:
            self.requests = []
    
    def save_data(self):
        with open(self.books_file, 'w') as f:
            json.dump(self.books, f, indent=2)
        with open(self.users_file, 'w') as f:
            json.dump(self.users, f, indent=2)
        with open(self.requests_file, 'w') as f:
            json.dump(self.requests, f, indent=2)
    
    def display_menu(self):
        print("\n" + "="*50)
        print("LIBRARY LENDING SYSTEM")
        print("="*50)
        print("1. Manage Books")
        print("2. Add Book")
        print("3. Manage Users")
        print("4. Manage Requests")
        print("5. Generate Reports")
        print("6. Exit")
        print("="*50)
    
    def run(self):
        while True:
            self.display_menu()
            choice = input("\nEnter your choice (1-6): ")
            
            if choice == '1':
                self.manage_books()
            elif choice == '2':
                self.add_book()
            elif choice == '3':
                self.manage_users()
            elif choice == '4':
                self.manage_requests()
            elif choice == '5':
                self.generate_reports()
            elif choice == '6':
                self.save_data()
                print("\nThank you for using Library Lending System!")
                break
            else:
                print("\nInvalid choice! Please try again.")
    
    # OPTION 1: MANAGE BOOKS
    def manage_books(self):
        while True:
            print("\n" + "="*50)
            print("MANAGE BOOKS")
            print("="*50)
            print("1. View All Books")
            print("2. Search Book")
            print("3. Update Book Details")
            print("4. Delete Book")
            print("5. Check Book Availability")
            print("6. Back to Main Menu")
            
            choice = input("\nEnter your choice (1-6): ")
            
            if choice == '1':
                self.view_all_books()
            elif choice == '2':
                self.search_book()
            elif choice == '3':
                self.update_book()
            elif choice == '4':
                self.delete_book()
            elif choice == '5':
                self.check_availability()
            elif choice == '6':
                break
            else:
                print("Invalid choice!")
    
    def view_all_books(self):
        print("\n" + "="*80)
        print(f"{'ID':<5} {'Title':<30} {'Author':<20} {'Status':<10} {'ISBN':<15}")
        print("="*80)
        for book in self.books:
            print(f"{book.get('id', ''):<5} {book.get('title', '')[:28]:<30} "
                  f"{book.get('author', '')[:18]:<20} {book.get('status', ''):<10} "
                  f"{book.get('isbn', ''):<15}")
    
    def search_book(self):
        search_term = input("\nEnter title, author, or ISBN to search: ").lower()
        results = []
        for book in self.books:
            if (search_term in book.get('title', '').lower() or
                search_term in book.get('author', '').lower() or
                search_term in book.get('isbn', '').lower()):
                results.append(book)
        
        if results:
            print(f"\nFound {len(results)} book(s):")
            print("="*80)
            print(f"{'ID':<5} {'Title':<30} {'Author':<20} {'Status':<10}")
            print("="*80)
            for book in results:
                print(f"{book.get('id', ''):<5} {book.get('title', '')[:28]:<30} "
                      f"{book.get('author', '')[:18]:<20} {book.get('status', ''):<10}")
        else:
            print("No books found!")
    
    def update_book(self):
        book_id = input("Enter Book ID to update: ")
        for book in self.books:
            if str(book.get('id')) == book_id:
                print("\nCurrent Details:")
                print(f"Title: {book.get('title')}")
                print(f"Author: {book.get('author')}")
                print(f"ISBN: {book.get('isbn')}")
                print(f"Status: {book.get('status')}")
                
                print("\nEnter new details (press Enter to keep current):")
                new_title = input(f"Title [{book.get('title')}]: ") or book.get('title')
                new_author = input(f"Author [{book.get('author')}]: ") or book.get('author')
                new_isbn = input(f"ISBN [{book.get('isbn')}]: ") or book.get('isbn')
                new_status = input(f"Status (Available/Borrowed) [{book.get('status')}]: ") or book.get('status')
                
                book['title'] = new_title
                book['author'] = new_author
                book['isbn'] = new_isbn
                book['status'] = new_status
                
                print("Book updated successfully!")
                self.save_data()
                return
        
        print("Book not found!")
    
    def delete_book(self):
        book_id = input("Enter Book ID to delete: ")
        for i, book in enumerate(self.books):
            if str(book.get('id')) == book_id:
                if book.get('status') == 'Borrowed':
                    print("Cannot delete borrowed book!")
                    return
                
                confirm = input(f"Delete '{book.get('title')}'? (y/n): ").lower()
                if confirm == 'y':
                    del self.books[i]
                    print("Book deleted successfully!")
                    self.save_data()
                return
        
        print("Book not found!")
    
    def check_availability(self):
        book_id = input("Enter Book ID to check: ")
        for book in self.books:
            if str(book.get('id')) == book_id:
                print(f"\nBook: {book.get('title')}")
                print(f"Status: {book.get('status')}")
                
                # Check if book is borrowed
                if book.get('status') == 'Borrowed':
                    for request in self.requests:
                        if (request.get('book_id') == book_id and 
                            request.get('status') == 'Active'):
                            due_date = request.get('due_date')
                            borrower = request.get('user_id')
                            print(f"Borrowed by: User {borrower}")
                            print(f"Due Date: {due_date}")
                return
        
        print("Book not found!")
    
    # OPTION 2: ADD BOOK
    def add_book(self):
        print("\n" + "="*50)
        print("ADD NEW BOOK")
        print("="*50)
        
        # Generate new ID
        if self.books:
            new_id = max(int(book['id']) for book in self.books) + 1
        else:
            new_id = 1
        
        title = input("Enter book title: ")
        author = input("Enter author: ")
        isbn = input("Enter ISBN: ")
        category = input("Enter category: ")
        year = input("Enter publication year: ")
        
        new_book = {
            'id': new_id,
            'title': title,
            'author': author,
            'isbn': isbn,
            'category': category,
            'year': year,
            'status': 'Available',
            'date_added': datetime.now().strftime("%Y-%m-%d")
        }
        
        self.books.append(new_book)
        self.save_data()
        print(f"\nBook '{title}' added successfully with ID: {new_id}")
    
    # OPTION 3: MANAGE USERS
    def manage_users(self):
        while True:
            print("\n" + "="*50)
            print("MANAGE USERS")
            print("="*50)
            print("1. View All Users")
            print("2. Add New User")
            print("3. Update User")
            print("4. Delete User")
            print("5. View User Borrowing History")
            print("6. Back to Main Menu")
            
            choice = input("\nEnter your choice (1-6): ")
            
            if choice == '1':
                self.view_all_users()
            elif choice == '2':
                self.add_user()
            elif choice == '3':
                self.update_user()
            elif choice == '4':
                self.delete_user()
            elif choice == '5':
                self.view_user_history()
            elif choice == '6':
                break
            else:
                print("Invalid choice!")
    
    def view_all_users(self):
        print("\n" + "="*70)
        print(f"{'ID':<5} {'Name':<20} {'Email':<25} {'Phone':<15}")
        print("="*70)
        for user in self.users:
            print(f"{user.get('id', ''):<5} {user.get('name', '')[:18]:<20} "
                  f"{user.get('email', '')[:23]:<25} {user.get('phone', ''):<15}")
    
    def add_user(self):
        print("\nADD NEW USER")
        print("-"*30)
        
        if self.users:
            new_id = max(int(user['id']) for user in self.users) + 1
        else:
            new_id = 1
        
        name = input("Enter full name: ")
        email = input("Enter email: ")
        phone = input("Enter phone: ")
        address = input("Enter address: ")
        
        new_user = {
            'id': new_id,
            'name': name,
            'email': email,
            'phone': phone,
            'address': address,
            'date_joined': datetime.now().strftime("%Y-%m-%d"),
            'active_borrowings': 0,
            'total_borrowings': 0
        }
        
        self.users.append(new_user)
        self.save_data()
        print(f"\nUser '{name}' added successfully with ID: {new_id}")
    
    def update_user(self):
        user_id = input("Enter User ID to update: ")
        for user in self.users:
            if str(user.get('id')) == user_id:
                print("\nCurrent Details:")
                print(f"Name: {user.get('name')}")
                print(f"Email: {user.get('email')}")
                print(f"Phone: {user.get('phone')}")
                print(f"Address: {user.get('address')}")
                
                print("\nEnter new details (press Enter to keep current):")
                user['name'] = input(f"Name [{user.get('name')}]: ") or user.get('name')
                user['email'] = input(f"Email [{user.get('email')}]: ") or user.get('email')
                user['phone'] = input(f"Phone [{user.get('phone')}]: ") or user.get('phone')
                user['address'] = input(f"Address [{user.get('address')}]: ") or user.get('address')
                
                print("User updated successfully!")
                self.save_data()
                return
        
        print("User not found!")
    
    def delete_user(self):
        user_id = input("Enter User ID to delete: ")
        for i, user in enumerate(self.users):
            if str(user.get('id')) == user_id:
                # Check if user has active borrowings
                if user.get('active_borrowings', 0) > 0:
                    print("Cannot delete user with active borrowings!")
                    return
                
                confirm = input(f"Delete '{user.get('name')}'? (y/n): ").lower()
                if confirm == 'y':
                    del self.users[i]
                    print("User deleted successfully!")
                    self.save_data()
                return
        
        print("User not found!")
    
    def view_user_history(self):
        user_id = input("Enter User ID: ")
        
        # Find user
        user = None
        for u in self.users:
            if str(u.get('id')) == user_id:
                user = u
                break
        
        if not user:
            print("User not found!")
            return
        
        print(f"\nBorrowing History for {user.get('name')}")
        print("="*70)
        print(f"Active Borrowings: {user.get('active_borrowings', 0)}")
        print(f"Total Borrowings: {user.get('total_borrowings', 0)}")
        print("\nCurrent Borrowings:")
        print("-"*70)
        
        has_active = False
        for request in self.requests:
            if (request.get('user_id') == user_id and 
                request.get('status') == 'Active'):
                has_active = True
                book_id = request.get('book_id')
                book = self.get_book_by_id(book_id)
                if book:
                    print(f"Book: {book.get('title')}")
                    print(f"Borrowed on: {request.get('borrow_date')}")
                    print(f"Due on: {request.get('due_date')}")
                    print("-"*40)
        
        if not has_active:
            print("No active borrowings")
    
    def get_book_by_id(self, book_id):
        for book in self.books:
            if str(book.get('id')) == str(book_id):
                return book
        return None
    
    # OPTION 4: MANAGE REQUESTS
    def manage_requests(self):
        while True:
            print("\n" + "="*50)
            print("MANAGE REQUESTS")
            print("="*50)
            print("1. View All Requests")
            print("2. Borrow Book")
            print("3. Return Book")
            print("4. Renew Book")
            print("5. View Overdue Books")
            print("6. Back to Main Menu")
            
            choice = input("\nEnter your choice (1-6): ")
            
            if choice == '1':
                self.view_all_requests()
            elif choice == '2':
                self.borrow_book()
            elif choice == '3':
                self.return_book()
            elif choice == '4':
                self.renew_book()
            elif choice == '5':
                self.view_overdue()
            elif choice == '6':
                break
            else:
                print("Invalid choice!")
    
    def view_all_requests(self):
        print("\n" + "="*100)
        print(f"{'Req ID':<8} {'User':<15} {'Book':<25} {'Status':<12} {'Borrowed':<12} {'Due':<12}")
        print("="*100)
        
        for request in self.requests:
            user_id = request.get('user_id')
            book_id = request.get('book_id')
            
            user_name = self.get_user_name(user_id)
            book_title = self.get_book_title(book_id)
            
            print(f"{request.get('id', ''):<8} {user_name[:13]:<15} {book_title[:23]:<25} "
                  f"{request.get('status', ''):<12} {request.get('borrow_date', ''):<12} "
                  f"{request.get('due_date', ''):<12}")
    
    def get_user_name(self, user_id):
        for user in self.users:
            if str(user.get('id')) == str(user_id):
                return user.get('name', 'Unknown')
        return 'Unknown'
    
    def get_book_title(self, book_id):
        for book in self.books:
            if str(book.get('id')) == str(book_id):
                return book.get('title', 'Unknown')
        return 'Unknown'
    
    def borrow_book(self):
        print("\nBORROW BOOK")
        print("-"*30)
        
        user_id = input("Enter User ID: ")
        book_id = input("Enter Book ID: ")
        
        # Check if user exists
        user = None
        for u in self.users:
            if str(u.get('id')) == user_id:
                user = u
                break
        
        if not user:
            print("User not found!")
            return
        
        # Check if book exists and is available
        book = None
        for b in self.books:
            if str(b.get('id')) == book_id:
                book = b
                break
        
        if not book:
            print("Book not found!")
            return
        
        if book.get('status') != 'Available':
            print(f"Book is not available. Status: {book.get('status')}")
            return
        
        # Check user's active borrowings
        if user.get('active_borrowings', 0) >= 5:  # Limit to 5 books
            print("User has reached maximum borrowing limit (5 books)")
            return
        
        # Generate request ID
        if self.requests:
            req_id = max(int(req['id']) for req in self.requests) + 1
        else:
            req_id = 1
        
        borrow_date = datetime.now().strftime("%Y-%m-%d")
        due_date = (datetime.now() + timedelta(days=14)).strftime("%Y-%m-%d")  # 2 weeks
        
        new_request = {
            'id': req_id,
            'user_id': user_id,
            'book_id': book_id,
            'borrow_date': borrow_date,
            'due_date': due_date,
            'status': 'Active',
            'renewals': 0
        }
        
        # Update book status
        book['status'] = 'Borrowed'
        
        # Update user stats
        user['active_borrowings'] = user.get('active_borrowings', 0) + 1
        user['total_borrowings'] = user.get('total_borrowings', 0) + 1
        
        self.requests.append(new_request)
        self.save_data()
        
        print(f"\nBook '{book.get('title')}' borrowed successfully!")
        print(f"Due Date: {due_date}")
        print(f"Request ID: {req_id}")
    
    def return_book(self):
        print("\nRETURN BOOK")
        print("-"*30)
        
        req_id = input("Enter Request ID: ")
        
        # Find request
        request = None
        for req in self.requests:
            if str(req.get('id')) == req_id and req.get('status') == 'Active':
                request = req
                break
        
        if not request:
            print("Active request not found!")
            return
        
        user_id = request.get('user_id')
        book_id = request.get('book_id')
        
        # Update request status
        request['status'] = 'Returned'
        request['return_date'] = datetime.now().strftime("%Y-%m-%d")
        
        # Update book status
        for book in self.books:
            if str(book.get('id')) == book_id:
                book['status'] = 'Available'
                book_title = book.get('title')
                break
        
        # Update user stats
        for user in self.users:
            if str(user.get('id')) == user_id:
                user['active_borrowings'] = max(0, user.get('active_borrowings', 0) - 1)
                break
        
        self.save_data()
        print(f"\nBook '{book_title}' returned successfully!")
        
        # Check for overdue
        due_date = datetime.strptime(request.get('due_date'), "%Y-%m-%d")
        return_date = datetime.strptime(request.get('return_date'), "%Y-%m-%d")
        
        if return_date > due_date:
            days_overdue = (return_date - due_date).days
            fine = days_overdue * 1.00  # $1 per day
            print(f"Book was {days_overdue} days overdue")
            print(f"Fine amount: ${fine:.2f}")
    
    def renew_book(self):
        print("\nRENEW BOOK")
        print("-"*30)
        
        req_id = input("Enter Request ID: ")
        
        # Find request
        request = None
        for req in self.requests:
            if str(req.get('id')) == req_id and req.get('status') == 'Active':
                request = req
                break
        
        if not request:
            print("Active request not found!")
            return
        
        # Check renewals limit
        if request.get('renewals', 0) >= 2:
            print("Maximum renewals (2) reached!")
            return
        
        # Update due date
        current_due = datetime.strptime(request.get('due_date'), "%Y-%m-%d")
        new_due = current_due + timedelta(days=7)  # 1 week extension
        request['due_date'] = new_due.strftime("%Y-%m-%d")
        request['renewals'] = request.get('renewals', 0) + 1
        
        self.save_data()
        print(f"Book renewed successfully!")
        print(f"New due date: {request['due_date']}")
        print(f"Renewals used: {request['renewals']}")
    
    def view_overdue(self):
        print("\nOVERDUE BOOKS")
        print("="*70)
        today = datetime.now().strftime("%Y-%m-%d")
        
        overdue_count = 0
        for request in self.requests:
            if (request.get('status') == 'Active' and 
                request.get('due_date') < today):
                overdue_count += 1
                
                user_id = request.get('user_id')
                book_id = request.get('book_id')
                
                user_name = self.get_user_name(user_id)
                book_title = self.get_book_title(book_id)
                
                print(f"User: {user_name}")
                print(f"Book: {book_title}")
                print(f"Due Date: {request.get('due_date')} (OVERDUE)")
                print(f"Request ID: {request.get('id')}")
                print("-"*40)
        
        if overdue_count == 0:
            print("No overdue books!")
        else:
            print(f"\nTotal overdue books: {overdue_count}")
    
    # OPTION 5: GENERATE REPORTS
    def generate_reports(self):
        while True:
            print("\n" + "="*50)
            print("GENERATE REPORTS")
            print("="*50)
            print("1. Library Statistics")
            print("2. Most Popular Books")
            print("3. User Activity Report")
            print("4. Overdue Analysis")
            print("5. Export All Data")
            print("6. Back to Main Menu")
            
            choice = input("\nEnter your choice (1-6): ")
            
            if choice == '1':
                self.library_statistics()
            elif choice == '2':
                self.most_popular_books()
            elif choice == '3':
                self.user_activity_report()
            elif choice == '4':
                self.overdue_analysis()
            elif choice == '5':
                self.export_all_data()
            elif choice == '6':
                break
            else:
                print("Invalid choice!")
    
    def library_statistics(self):
        print("\n" + "="*50)
        print("LIBRARY STATISTICS")
        print("="*50)
        
        total_books = len(self.books)
        available_books = sum(1 for book in self.books if book.get('status') == 'Available')
        borrowed_books = sum(1 for book in self.books if book.get('status') == 'Borrowed')
        total_users = len(self.users)
        active_requests = sum(1 for req in self.requests if req.get('status') == 'Active')
        
        print(f"Total Books: {total_books}")
        print(f"Available Books: {available_books}")
        print(f"Borrowed Books: {borrowed_books}")
        print(f"Total Users: {total_users}")
        print(f"Active Borrowings: {active_requests}")
        
        # Categories distribution
        categories = {}
        for book in self.books:
            category = book.get('category', 'Uncategorized')
            categories[category] = categories.get(category, 0) + 1
        
        if categories:
            print("\nBooks by Category:")
            for category, count in categories.items():
                print(f"  {category}: {count}")
    
    def most_popular_books(self):
        print("\n" + "="*50)
        print("MOST POPULAR BOOKS")
        print("="*50)
        
        # Count borrowings per book
        book_borrowings = {}
        for request in self.requests:
            book_id = request.get('book_id')
            book_borrowings[book_id] = book_borrowings.get(book_id, 0) + 1
        
        # Sort by borrow count
        sorted_books = sorted(book_borrowings.items(), key=lambda x: x[1], reverse=True)
        
        print(f"{'Rank':<6} {'Book':<30} {'Borrowings':<12}")
        print("-"*50)
        
        for i, (book_id, count) in enumerate(sorted_books[:10], 1):  # Top 10
            book = self.get_book_by_id(book_id)
            if book:
                print(f"{i:<6} {book.get('title', 'Unknown')[:28]:<30} {count:<12}")
    
    def user_activity_report(self):
        print("\n" + "="*50)
        print("USER ACTIVITY REPORT")
        print("="*50)
        
        print(f"{'User':<20} {'Active':<10} {'Total':<10}")
        print("-"*50)
        
        for user in self.users:
            print(f"{user.get('name', '')[:18]:<20} "
                  f"{user.get('active_borrowings', 0):<10} "
                  f"{user.get('total_borrowings', 0):<10}")
    
    def overdue_analysis(self):
        print("\n" + "="*50)
        print("OVERDUE ANALYSIS")
        print("="*50)
        
        today = datetime.now()
        overdue_total = 0
        overdue_by_days = {}
        
        for request in self.requests:
            if request.get('status') == 'Active':
                due_date = datetime.strptime(request.get('due_date'), "%Y-%m-%d")
                if due_date < today:
                    days_overdue = (today - due_date).days
                    overdue_total += 1
                    overdue_by_days[days_overdue] = overdue_by_days.get(days_overdue, 0) + 1
        
        print(f"Total Overdue Books: {overdue_total}")
        
        if overdue_by_days:
            print("\nOverdue Distribution:")
            for days, count in sorted(overdue_by_days.items()):
                print(f"  {days} day(s) overdue: {count} book(s)")
    
    def export_all_data(self):
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        
        # Export books
        books_filename = f"books_export_{timestamp}.csv"
        with open(books_filename, 'w') as f:
            f.write("ID,Title,Author,ISBN,Category,Year,Status,Date Added\n")
            for book in self.books:
                f.write(f"{book.get('id')},\"{book.get('title')}\",\"{book.get('author')}\","
                       f"{book.get('isbn')},{book.get('category')},{book.get('year')},"
                       f"{book.get('status')},{book.get('date_added')}\n")
        
        # Export users
        users_filename = f"users_export_{timestamp}.csv"
        with open(users_filename, 'w') as f:
            f.write("ID,Name,Email,Phone,Address,Date Joined,Active Borrowings,Total Borrowings\n")
            for user in self.users:
                f.write(f"{user.get('id')},\"{user.get('name')}\",{user.get('email')},"
                       f"{user.get('phone')},\"{user.get('address')}\",{user.get('date_joined')},"
                       f"{user.get('active_borrowings')},{user.get('total_borrowings')}\n")
        
        # Export requests
        requests_filename = f"requests_export_{timestamp}.csv"
        with open(requests_filename, 'w') as f:
            f.write("ID,User ID,Book ID,Borrow Date,Due Date,Return Date,Status,Renewals\n")
            for req in self.requests:
                f.write(f"{req.get('id')},{req.get('user_id')},{req.get('book_id')},"
                       f"{req.get('borrow_date')},{req.get('due_date')},{req.get('return_date', '')},"
                       f"{req.get('status')},{req.get('renewals', 0)}\n")
        
        print(f"\nData exported successfully!")
        print(f"Books: {books_filename}")
        print(f"Users: {users_filename}")
        print(f"Requests: {requests_filename}")


# Main program
if __name__ == "__main__":
    # Initialize sample data if files don't exist
    if not os.path.exists("books.json"):
        sample_books = [
            {
                "id": 1,
                "title": "The Great Gatsby",
                "author": "F. Scott Fitzgerald",
                "isbn": "9780743273565",
                "category": "Fiction",
                "year": "1925",
                "status": "Available",
                "date_added": "2024-01-15"
            },
            {
                "id": 2,
                "title": "To Kill a Mockingbird",
                "author": "Harper Lee",
                "isbn": "9780061120084",
                "category": "Fiction",
                "year": "1960",
                "status": "Available",
                "date_added": "2024-01-20"
            },
            {
                "id": 3,
                "title": "1984",
                "author": "George Orwell",
                "isbn": "9780451524935",
                "category": "Science Fiction",
                "year": "1949",
                "status": "Borrowed",
                "date_added": "2024-02-10"
            }
        ]
        with open("books.json", 'w') as f:
            json.dump(sample_books, f, indent=2)
    
    if not os.path.exists("users.json"):
        sample_users = [
            {
                "id": 1,
                "name": "John Doe",
                "email": "john@example.com",
                "phone": "123-456-7890",
                "address": "123 Main St",
                "date_joined": "2024-01-10",
                "active_borrowings": 1,
                "total_borrowings": 3
            },
            {
                "id": 2,
                "name": "Jane Smith",
                "email": "jane@example.com",
                "phone": "987-654-3210",
                "address": "456 Oak Ave",
                "date_joined": "2024-02-01",
                "active_borrowings": 0,
                "total_borrowings": 2
            }
        ]
        with open("users.json", 'w') as f:
            json.dump(sample_users, f, indent=2)
    
    if not os.path.exists("requests.json"):
        sample_requests = [
            {
                "id": 1,
                "user_id": "1",
                "book_id": "3",
                "borrow_date": "2024-03-01",
                "due_date": "2024-03-15",
                "status": "Active",
                "renewals": 0
            }
        ]
        with open("requests.json", 'w') as f:
            json.dump(sample_requests, f, indent=2)
    
    # Run the system
    system = LibraryLenderSystem()
    system.run()